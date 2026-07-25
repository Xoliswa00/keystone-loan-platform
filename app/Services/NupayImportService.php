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
use PhpOffice\PhpSpreadsheet\IOFactory;

class NupayImportService
{
    protected array $errors = [];

    protected array $warnings = [];

    protected array $skipped = [];

    /**
     * Parse, stage and persist a NuPay file (CSV or Excel).
     * Creates an import_batch record and inserts rows into nupay_transactions_stagings.
     * Replaces the manual Python → SQL INSERT workflow — including the manual
     * step that workflow never automated: NuPay's export is a 4-tab workbook
     * (Success / Failed / Tracking / Reversed) with no type column in any tab
     * — the tab itself IS the type. The old process required a human to
     * merge all 4 tabs into one sheet and hand-type a transaction_type column
     * before the Python script would touch it. This reads every tab directly
     * and tags each row from its own tab name instead.
     *
     * @param  string  $filePath  Absolute path to uploaded file
     * @param  string  $originalName  Original filename
     * @param  int  $uploadedBy  User ID
     * @param  string|null  $manualTransactionType  Required for CSV/TXT uploads
     *                                              only — a flat file has no tab to infer the type from, so the
     *                                              uploader must state it (success|failed|tracking|reversed).
     *                                              Ignored for Excel uploads, which self-identify per tab.
     */
    public function importAndStage(string $filePath, string $originalName, int $uploadedBy, ?string $manualTransactionType = null): import_batch
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
            $rows = $this->parseFile($filePath, $originalName, $manualTransactionType);
        } catch (Exception $e) {
            $batch->update(['status' => 'FAILED_CAPTURE', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        // ── 6. Stage rows ──────────────────────────────────────────────────────
        $staged = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $item) {
                $cleanedRow = null;

                try {
                    $cleanedRow = $this->cleanRow($item['data'], $item['transaction_type']);
                    $key = $this->rowKey($cleanedRow);

                    if ($this->existsInDatabase($cleanedRow)) {
                        $this->warnings[] = "Row {$index}: already imported (mandate_id={$cleanedRow['mandate_id']}), skipped.";
                        $this->skipped[] = $index;

                        continue;
                    }

                    nupay_transactions_staging::create(array_merge($cleanedRow, [
                        'import_ref' => $importRef,
                        'import_id' => $batch->id,
                        'raw_row_json' => json_encode($item['data']),
                    ]));

                    $staged++;

                } catch (Exception $e) {
                    // mandate_id identifies the actual failing record for
                    // whoever's reading the log — the row index alone means
                    // nothing once you're looking at the source file. Falls
                    // back to the raw (uncleaned) data if cleanRow() itself
                    // is what threw, so this context survives either failure
                    // point.
                    $mandateId = $cleanedRow['mandate_id'] ?? $item['data']['Mandate ID'] ?? $item['data']['mandate_id'] ?? 'unknown';
                    $type = $item['transaction_type'] ?? 'unknown';

                    $this->errors[] = "Row {$index} (mandate_id={$mandateId}, type={$type}): ".$e->getMessage();

                    Log::warning("NuPay staging row {$index} failed", [
                        'import_ref' => $importRef,
                        'mandate_id' => $mandateId,
                        'transaction_type' => $type,
                        'exception_class' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
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

    /**
     * @return Collection<int, array{data: array, transaction_type: string}>
     */
    protected function parseFile(string $filePath, string $originalName, ?string $manualTransactionType): Collection
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            if (! $manualTransactionType) {
                throw new Exception('Transaction type must be selected for CSV/TXT uploads — a flat file has no tab to infer it from.');
            }

            $type = $this->normalizeTransactionType($manualTransactionType) ?? strtolower(trim($manualTransactionType));

            return $this->parseCsv($filePath)
                ->map(fn ($row) => ['data' => $row, 'transaction_type' => $type]);
        }

        return $this->parseExcelSheets($filePath);
    }

    /**
     * NuPay's Excel export is a 4-tab workbook (Success / Failed / Tracking /
     * Reversed) — no tab has a column identifying its own type, because the
     * tab itself IS the type. Reads every tab and tags each row from its
     * sheet name rather than trusting only the first tab (which silently
     * dropped the other 3 previously).
     */
    protected function parseExcelSheets(string $filePath): Collection
    {
        $sheetNames = IOFactory::createReaderForFile($filePath)->listWorksheetNames($filePath);
        $sheets = Excel::toCollection(new GenericArrayImport, $filePath);

        $rows = collect();

        foreach ($sheets as $index => $sheetRows) {
            $sheetName = $sheetNames[$index] ?? "sheet {$index}";

            if ($sheetRows->isEmpty()) {
                continue;
            }

            $type = $this->normalizeTransactionType($sheetName);

            if (! $type) {
                $this->warnings[] = "Sheet '{$sheetName}' doesn't match a known transaction type ".
                    '(success/failed/tracking/reversed) — its rows were skipped.';

                continue;
            }

            // Each row from ToCollection/WithHeadingRow is itself a Collection,
            // not a plain array — (array) on an object just exposes its
            // internal properties, it doesn't call toArray(). Silently
            // produced garbage keys for every Excel upload before this fix.
            $this->validateHeaders(array_keys($sheetRows->first()->toArray()));

            foreach ($sheetRows as $row) {
                $rows->push(['data' => $row->toArray(), 'transaction_type' => $type]);
            }
        }

        if ($rows->isEmpty()) {
            throw new Exception('No recognisable transaction-type sheets found — expected tabs named '.
                'success/failed/tracking/reversed (or close variants).');
        }

        return $rows;
    }

    /**
     * Maps a sheet/tab name (or a manually-selected CSV type) to the
     * canonical transaction_type value NuPayService::postTransaction()
     * dispatches on. Tolerates the common "succes" (missing 's') tab-name
     * typo seen in real NuPay exports.
     */
    protected function normalizeTransactionType(string $label): ?string
    {
        $key = strtolower(trim($label));

        return match (true) {
            str_starts_with($key, 'succes') => 'success',
            str_starts_with($key, 'fail') => 'failed',
            str_starts_with($key, 'cancel') => 'canceled',
            str_starts_with($key, 'revers') => 'reversed',
            str_starts_with($key, 'track') => 'tracking',
            default => null,
        };
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

    /**
     * Header/column names are compared with all non-alphanumeric characters
     * stripped and case folded — Laravel Excel's WithHeadingRow auto-slugifies
     * headers for .xlsx/.xls uploads ("Mandate ID" -> "mandate_id"), while the
     * hand-rolled CSV parser below keeps them verbatim ("Mandate ID"). Both
     * forms must match the same NupayColumnMap entries, so comparisons are
     * normalised on both sides rather than assuming one particular format.
     */
    private function normalizeHeader(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($s)));
    }

    protected function validateHeaders(array $uploadedHeaders): void
    {
        // Nu-Pay sometimes exports with slightly different header capitalisation
        $critical = ['Mandate ID', 'Debtor ID', 'Instalment Amount', 'Action Date', 'Status'];
        $normalizedUploaded = array_map(fn ($h) => $this->normalizeHeader((string) $h), $uploadedHeaders);

        foreach ($critical as $required) {
            if (! in_array($this->normalizeHeader($required), $normalizedUploaded, true)) {
                throw new Exception("Required column '{$required}' not found in file.");
            }
        }
    }

    protected function cleanRow(array $row, string $transactionType): array
    {
        // Normalise every row key once, rather than re-normalising it for
        // each of the ~47 mapped columns.
        $normalizedRow = [];
        foreach ($row as $k => $v) {
            $normalizedRow[$this->normalizeHeader((string) $k)] = $v;
        }

        // Not sourced from NupayColumnMap: no NuPay export tab has a column
        // identifying its own type (the tab name IS the type) — see
        // parseExcelSheets()/parseFile().
        $data = ['transaction_type' => $transactionType];

        // ID-like fields must never round-trip through a float — PhpSpreadsheet
        // returns numeric-looking Excel cells as floats, and a long SA ID/
        // account/branch number can come out in scientific notation otherwise.
        $idFields = ['debtor_id', 'debtor_account_number', 'debtor_branch_number', 'merchant_number'];

        foreach (NupayColumnMap::map() as $excel => $db) {
            $value = $normalizedRow[$this->normalizeHeader($excel)] ?? null;

            $data[$db] = match (true) {
                // Anchored to underscore-delimited segments, not a bare
                // substring — "mandate_id" contains the letters "date" (from
                // "man-DATE-_id"), which a plain str_contains($db, 'date')
                // wrongly caught, routing mandate_id/mandate_request_tran_id/
                // mandate_reference_number through the date cleaner instead
                // of leaving them as plain values.
                preg_match('/(?:^|_)date_time(?:$|_)/', $db) === 1 => NupayCleaner::dateTime($value),
                preg_match('/(?:^|_)date(?:$|_)/', $db) === 1 => NupayCleaner::date($value),
                str_contains($db, 'amount') => NupayCleaner::amount($value),
                in_array($db, $idFields, true) => NupayCleaner::idString($value),
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
