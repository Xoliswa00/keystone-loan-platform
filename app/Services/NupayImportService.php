<?php

namespace App\Services;

use App\Support\NupayColumnMap;
use App\Support\NupayCleaner;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\GenericArrayImport;

use Exception;

class NupayImportService
{
    protected array $errors = [];
    protected array $warnings = [];

    /**
     * Process a NuPay Excel file
     *
     * @param string $filePath Absolute path to the uploaded Excel/CSV file
     * @return Collection Cleaned, mapped rows
     * @throws Exception
     */
    public function import(string $filePath): Collection
    {
        $rows = Excel::toCollection(new GenericArrayImport, $filePath)->first();

        if ($rows->isEmpty()) {
            throw new Exception('Uploaded file contains no rows.');
        }

        $uploadedHeaders = $rows->keys()->toArray();
        $missingHeaders = array_diff(NupayColumnMap::expectedHeaders(), $uploadedHeaders);

        if (!empty($missingHeaders)) {
            throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
        }

        $cleanedRows = collect();

        foreach ($rows as $index => $row) {
            try {
                $cleanedRow = $this->cleanRow($row);
                
                if ($this->isDuplicate($cleanedRow)) {
                    $this->warnings[] = "Duplicate row at index {$index}: " . json_encode($cleanedRow);
                    continue; // skip duplicates
                }

                $cleanedRows->push($cleanedRow);

            } catch (Exception $e) {
                $this->errors[] = "Row {$index} error: " . $e->getMessage();
            }
        }

        if (!empty($this->errors)) {
            throw new Exception('Import failed: ' . implode('; ', $this->errors));
        }

        return $cleanedRows;
    }

    /**
     * Clean and map a single row
     */
    protected function cleanRow(array $row): array
    {
        $data = [];

        foreach (NupayColumnMap::map() as $excel => $db) {
            $value = $row[$excel] ?? null;

            if (str_contains($db, 'date_time')) {
                $data[$db] = NupayCleaner::dateTime($value);
            } elseif (str_contains($db, 'date')) {
                $data[$db] = NupayCleaner::date($value);
            } elseif (str_contains($db, 'amount')) {
                $data[$db] = NupayCleaner::amount($value);
            } else {
                $data[$db] = NupayCleaner::string($value);
            }
        }

        return $data;
    }

    /**
     * Detect duplicates based on business keys
     */
    protected function isDuplicate(array $row): bool
    {
        $key = $row['mandate_id'] . '|' . $row['mandate_request_tran_id'] . '|' . $row['contract_reference'];

        // This can check in DB or an in-memory array
        static $seen = [];
        if (isset($seen[$key])) {
            return true;
        }

        $seen[$key] = true;
        return false;
    }

    /**
     * Retrieve import warnings
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
