<?php

namespace App\Services;

use App\Support\NupayColumnMap;
use App\Support\NupayCleaner;
use App\Models\nupay_transactions_staging;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\GenericArrayImport;
use Exception;

class NupayImportService
{
    protected array $errors   = [];
    protected array $warnings = [];
    protected array $skipped  = [];

    /**
     * Parse and stage a NuPay Excel file.
     *
     * Returns a collection of staged rows.
     * Rows with errors are skipped and logged — the import does NOT
     * fail if some rows are bad (partial import is better than no import).
     * DB-level duplicate detection prevents double-posting.
     */
    public function import(string $filePath, string $importRef): Collection
    {
        $rows = Excel::toCollection(new GenericArrayImport, $filePath)->first();

        if (!$rows || $rows->isEmpty()) {
            throw new Exception('Uploaded file contains no rows.');
        }

        // Validate headers on the first row's keys
        $uploadedHeaders = array_keys($rows->first() ?? []);
        $missingHeaders  = array_diff(NupayColumnMap::expectedHeaders(), $uploadedHeaders);

        if (!empty($missingHeaders)) {
            throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
        }

        $staged = collect();

        foreach ($rows as $index => $row) {
            try {
                $cleanedRow = $this->cleanRow((array) $row);
                $key        = $this->rowKey($cleanedRow);

                // ── In-memory duplicate (current import) ──
                if ($staged->contains('_key', $key)) {
                    $this->warnings[] = "Row {$index}: duplicate within this file, skipped.";
                    $this->skipped[]  = $index;
                    continue;
                }

                // ── Database duplicate (previous imports) ──
                if ($this->existsInDatabase($cleanedRow)) {
                    $this->warnings[] = "Row {$index}: already imported (mandate_id={$cleanedRow['mandate_id']}), skipped.";
                    $this->skipped[]  = $index;
                    continue;
                }

                $cleanedRow['import_ref'] = $importRef;
                $cleanedRow['_key']       = $key;

                $staged->push($cleanedRow);

            } catch (Exception $e) {
                // Log and continue — partial import
                $this->errors[] = "Row {$index}: " . $e->getMessage();
                Log::warning("NuPay import row {$index} skipped: " . $e->getMessage(), [
                    'import_ref' => $importRef,
                    'row'        => $row,
                ]);
            }
        }

        if ($staged->isEmpty() && !empty($this->errors)) {
            throw new Exception('Every row failed validation: ' . implode('; ', array_slice($this->errors, 0, 5)));
        }

        return $staged->map(fn($row) => collect($row)->except('_key')->toArray());
    }

    public function errors():   array { return $this->errors;   }
    public function warnings(): array { return $this->warnings; }
    public function skipped():  array { return $this->skipped;  }

    // ──────────────────────────────────────────────────────────────────────────

    protected function cleanRow(array $row): array
    {
        $data = [];

        foreach (NupayColumnMap::map() as $excel => $db) {
            $value = $row[$excel] ?? null;

            $data[$db] = match(true) {
                str_contains($db, 'date_time') => NupayCleaner::dateTime($value),
                str_contains($db, 'date')      => NupayCleaner::date($value),
                str_contains($db, 'amount')    => NupayCleaner::amount($value),
                default                        => NupayCleaner::string($value),
            };
        }

        return $data;
    }

    /**
     * Unique business key for a row — used for both in-memory and DB duplicate checks.
     */
    protected function rowKey(array $row): string
    {
        return implode('|', [
            $row['mandate_id']              ?? '',
            $row['mandate_request_tran_id'] ?? '',
            $row['contract_reference']      ?? '',
        ]);
    }

    /**
     * Check the database for a previously imported row with the same business keys.
     * Prevents re-importing the same file or overlapping date ranges.
     */
    protected function existsInDatabase(array $row): bool
    {
        return nupay_transactions_staging::where('mandate_id', $row['mandate_id'] ?? null)
            ->where('mandate_request_tran_id', $row['mandate_request_tran_id'] ?? null)
            ->where('contract_reference', $row['contract_reference'] ?? null)
            ->exists();
    }
}
