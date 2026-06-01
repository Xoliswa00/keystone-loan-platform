<?php

namespace App\Services;

use App\Models\import_batches as ImportBatch;
use App\Models\import_row as ImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ImportService
{
    /**
     * Entry point for all imports
     */
    public function import(string $source, UploadedFile $file): ImportBatch
    {
        DB::beginTransaction();

        try {
            // 1. Store file
            $storedPath = $file->store('imports');

            // 2. Create batch
            $batch = ImportBatch::create([
                'source'            => strtolower($source),
                'original_filename' => $file->getClientOriginalName(),
                'stored_path'       => $storedPath,
                'checksum'          => hash_file(
                    'sha256',
                    $file->getRealPath()
                ),
                'status'            => 'UPLOADED',
            ]);

            // 3. Read rows
            $rows = $this->readFile($batch->source, $storedPath);

            // 4. Persist raw rows
            foreach ($rows as $index => $row) {
                ImportRow::create([
                    'import_batch_id' => $batch->id,
                    'row_number'      => $index + 1,
                    'payload'         => $row,
                ]);
            }

            // 5. Finalize batch
            $batch->update([
                'row_count' => count($rows),
                'status'    => 'CAPTURED',
            ]);

            DB::commit();

            return $batch;

        } catch (Throwable $e) {
            DB::rollBack();

            if (isset($batch)) {
                $batch->update([
                    'status' => 'FAILED',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Route import based on source
     */
    protected function readFile(string $source, string $storedPath): array
    {
        return match ($source) {
            'nupay' => $this->readSpreadsheet($storedPath),
            'bank'  => $this->readSpreadsheet($storedPath), // future rules
            default => throw new \RuntimeException(
                "Unsupported import source: {$source}"
            ),
        };
    }

    /**
     * Generic spreadsheet reader (Excel / CSV)
     * Returns raw rows as associative arrays
     */
    protected function readSpreadsheet(string $storedPath): array
    {
        $fullPath = Storage::path($storedPath);

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        $headers = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = trim((string) $cell->getValue());
            }

            // First row = headers
            if ($rowIndex === 1) {
                $headers = $cells;
                continue;
            }

            // Skip empty rows
            if (count(array_filter($cells)) === 0) {
                continue;
            }

            // Combine headers + values
            $rows[] = array_combine(
                $headers,
                array_slice($cells, 0, count($headers))
            );
        }

        return $rows;
    }
}
