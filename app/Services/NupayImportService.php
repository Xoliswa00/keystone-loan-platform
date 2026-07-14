<?php

namespace App\Services;

use App\Imports\GenericArrayImport;
use App\Models\import_batch;
use App\Models\nupay_transactions_staging;
use App\Support\NupayCleaner;
use App\Support\NupayColumnMap;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class NupayImportService
{
    protected array $errors = [];

    protected array $warnings = [];

    protected array $skipped = [];

    /**
     * Parse, stage and persist a NuPay file (CSV or Excel).
     * Creates an import_batch record and inserts rows into nupay_transactions_stagings.
     * Replaces the manual Python → SQL INSERT workflow.
     *
     * @param  string  $filePath  Absolute path to uploaded file
     * @param  string  $originalName  Original filename
     * @param  int  $uploadedBy  User ID
     */
    public function importAndStage(string $filePath, string $originalName, int $uploadedBy): import_batch
    {
        // ── 1. Duplicate file detection (checksum) ─────────────────────────────
        $checksum = md5_file($filePath);
        $existing = import_batch::where('checksum', $checksum)->first();
        if ($existing) {
            throw new Exception("This file has already been imported. Batch: {$existing->import_ref}");
        }

        // ── 2. Generate import reference ───────────────────────────────────────
        $importRef = 'NP-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(4));

        // ── 3. Store the file permanently ──────────────────────────────────────
        $storedPath = "nupay_imports/{$importRef}/".basename($filePath);
        Storage::disk('local')->put($storedPath, file_get_contents($filePath));

        // ── 4. Create batch record ─────────────────────────────────────────────
        $batch = import_batch::create([
            'source' => 'nupay',
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'checksum' => $checksum,
            'status' => 'UPLOADED',
            'import_ref' => $importRef,
            'processed_by' => $uploadedBy,
            'row_count' => 0,
        ]);

        // ── 5. Parse rows ──────────────────────────────────────────────────────
        try {
            $rows = $this->parseFile($filePath, $originalName);
        } catch (Exception $e) {
            $batch->update(['status' => 'FAILED_CAPTURE', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        // ── 6. Stage rows ──────────────────────────────────────────────────────
        $staged = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $cleanedRow = $this->cleanRow((array) $row);
                    $key = $this->rowKey($cleanedRow);

                    if ($this->existsInDatabase($cleanedRow)) {
                        $this->warnings[] = "Row {$index}: already imported (mandate_id={$cleanedRow['mandate_id']}), skipped.";
                        $this->skipped[] = $index;

                        continue;
                    }

                    nupay_transactions_staging::create(array_merge($cleanedRow, [
                        'import_ref' => $importRef,
                        'import_id' => $batch->id,
                        'raw_row_json' => json_encode($row),
                    ]));

                    $staged++;

                } catch (Exception $e) {
                    $this->errors[] = "Row {$index}: ".$e->getMessage();
                    Log::warning("NuPay staging row {$index} failed: ".$e->getMessage(), [
                        'import_ref' => $importRef,
                    ]);
                }
            }

            $batch->update([
                'row_count' => $staged,
                'status' => $staged > 0 ? 'CAPTURED' : 'FAILED_CAPTURE',
                'meta' => json_encode([
                    'staged' => $staged,
                    'skipped' => count($this->skipped),
                    'errors' => count($this->errors),
                    'warnings' => count($this->warnings),
                ]),
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $batch->update(['status' => 'FAILED_CAPTURE', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        if ($staged === 0 && ! empty($this->errors)) {
            throw new Exception('All rows failed to stage: '.implode('; ', array_slice($this->errors, 0, 3)));
        }

        return $batch->fresh();
    }

    // ── Public accessors ────────────────────────────────────────────────────────

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function skipped(): array
    {
        return $this->skipped;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Parsing — CSV (native PHP) or Excel (Maatwebsite)
    // ──────────────────────────────────────────────────────────────────────────

    protected function parseFile(string $filePath, string $originalName): Collection
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->parseCsv($filePath);
        }

        // Excel (xlsx / xls)
        $rows = Excel::toCollection(new GenericArrayImport, $filePath)->first();

        if (! $rows || $rows->isEmpty()) {
            throw new Exception('Excel file contains no data rows.');
        }

        $this->validateHeaders(array_keys((array) $rows->first()));

        return $rows;
    }

    /**
     * Parse CSV using PHP's built-in fgetcsv — no external package needed.
     * Handles the NuPay CSV export format directly.
     */
    protected function parseCsv(string $filePath): Collection
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new Exception('Cannot open file for reading.');
        }

        $headers = null;
        $rows = collect();

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            // Skip completely empty lines
            if (empty(array_filter($line, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            if ($headers === null) {
                // First non-empty row = headers
                $headers = array_map('trim', $line);
                $this->validateHeaders($headers);

                continue;
            }

            // Pad or trim row to match header count
            $line = array_slice(array_pad($line, count($headers), null), 0, count($headers));
            $rows->push(array_combine($headers, $line));
        }

        fclose($handle);

        if ($rows->isEmpty()) {
            throw new Exception('CSV file contains no data rows after the header.');
        }

        return $rows;
    }

    protected function validateHeaders(array $uploadedHeaders): void
    {
        // Nu-Pay sometimes exports with slightly different header capitalisation
        // Do a case-insensitive check for the critical fields
        $critical = ['Mandate ID', 'Debtor ID', 'Instalment Amount', 'Action Date', 'Status'];
        $upperUploaded = array_map('strtolower', $uploadedHeaders);

        foreach ($critical as $required) {
            if (! in_array(strtolower($required), $upperUploaded)) {
                throw new Exception("Required column '{$required}' not found in file.");
            }
        }
    }

    protected function cleanRow(array $row): array
    {
        $data = [];

        foreach (NupayColumnMap::map() as $excel => $db) {
            // Case-insensitive column matching
            $value = null;
            foreach ($row as $k => $v) {
                if (strtolower(trim($k)) === strtolower($excel)) {
                    $value = $v;
                    break;
                }
            }

            $data[$db] = match (true) {
                str_contains($db, 'date_time') => NupayCleaner::dateTime($value),
                str_contains($db, 'date') => NupayCleaner::date($value),
                str_contains($db, 'amount') => NupayCleaner::amount($value),
                default => NupayCleaner::string($value),
            };
        }

        return $data;
    }

    protected function rowKey(array $row): string
    {
        return implode('|', [
            $row['mandate_id'] ?? '',
            $row['mandate_request_tran_id'] ?? '',
            $row['contract_reference'] ?? '',
        ]);
    }

    protected function existsInDatabase(array $row): bool
    {
        return nupay_transactions_staging::where('mandate_id', $row['mandate_id'] ?? null)
            ->where('mandate_request_tran_id', $row['mandate_request_tran_id'] ?? null)
            ->where('contract_reference', $row['contract_reference'] ?? null)
            ->exists();
    }
}
