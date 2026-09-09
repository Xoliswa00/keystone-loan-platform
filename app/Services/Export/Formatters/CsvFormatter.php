<?php

namespace App\Services\Export\Formatters;

use App\Models\ExportProfile;
use App\Support\ColumnFormatter;

/**
 * CSV / custom-delimited output. Columns and their order come straight from
 * $profile->columns; each value is rendered by ColumnFormatter and then guarded
 * against CSV/formula injection.
 */
class CsvFormatter implements Formatter
{
    public function format(iterable $rows, ExportProfile $profile): string
    {
        $columns = $profile->columns ?: [];
        $delimiter = $profile->delimiter ?: ',';
        $enclosure = $profile->enclosure ?: '"';
        $eol = $profile->line_ending === 'crlf' ? "\r\n" : "\n";

        $handle = fopen('php://temp', 'r+');

        if ($profile->include_header) {
            fputcsv(
                $handle,
                array_map(fn ($c) => $c['label'] ?? $c['source'], $columns),
                $delimiter,
                $enclosure,
                ''
            );
        }

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $c) {
                $source = $c['source'];
                $value = is_array($row) ? ($row[$source] ?? null) : ($row->{$source} ?? null);
                $line[] = $this->guard(ColumnFormatter::apply($value, $c['format'] ?? null));
            }
            fputcsv($handle, $line, $delimiter, $enclosure, '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // fputcsv always writes "\n"; rewrite once if the profile wants CRLF.
        return $eol === "\n" ? $csv : str_replace("\n", $eol, $csv);
    }

    /**
     * Neutralise a leading =, +, - or @ so a spreadsheet does not evaluate the
     * cell as a formula when the file is opened.
     */
    private function guard(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
