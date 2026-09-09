<?php

namespace App\Services\Export\Formatters;

use App\Models\ExportProfile;

interface Formatter
{
    /**
     * @param  iterable<array<string,mixed>|object>  $rows
     */
    public function format(iterable $rows, ExportProfile $profile): string;
}
