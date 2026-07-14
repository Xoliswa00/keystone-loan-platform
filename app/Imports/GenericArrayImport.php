<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Generic Excel/CSV importer that returns each row as an associative array
 * keyed by the heading row. Used by NupayImportService.
 *
 * WithHeadingRow: uses the first row as array keys (normalised to snake_case)
 *
 * Deliberately does NOT implement WithCalculatedFormulas — evaluating
 * formulas from an untrusted uploaded file is a formula-injection risk
 * (and a known source of PhpSpreadsheet CVEs). This importer reads raw
 * cell values only; expected input is plain financial data, not formulas.
 */
class GenericArrayImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): Collection
    {
        return $rows;
    }
}
