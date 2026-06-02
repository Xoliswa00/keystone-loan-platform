<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

/**
 * Generic Excel/CSV importer that returns each row as an associative array
 * keyed by the heading row. Used by NupayImportService.
 *
 * WithHeadingRow: uses the first row as array keys (normalised to snake_case)
 * WithCalculatedFormulas: resolves any Excel formulas before reading
 */
class GenericArrayImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public function collection(Collection $rows): Collection
    {
        return $rows;
    }
}
