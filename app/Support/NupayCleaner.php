<?php

namespace App\Support;

use Carbon\Carbon;

class NupayCleaner
{
    /**
     * Normalise date fields safely.
     */
    public static function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalise date-time fields safely.
     */
    public static function dateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalise monetary values.
     */
    public static function amount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? round((float) $value, 2)
            : null;
    }

    /**
     * Clean generic strings.
     */
    public static function string($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Clean ID-like fields (SA ID numbers, bank account numbers, branch
     * codes, merchant numbers) that must never be treated as numbers.
     * PhpSpreadsheet returns numeric-looking Excel cells as PHP floats, and a
     * naive (string) cast on a long one (e.g. a 13-digit SA ID) risks
     * scientific notation ("8.303190692087E+12") — the exact problem the
     * previous Python importer's float_to_int_str() existed to avoid. Whole-
     * number values are formatted via sprintf('%.0f', ...) instead of cast,
     * which is exact for any value within a double's 2^53 integer range
     * (comfortably covers real-world ID/account/branch numbers).
     */
    public static function idString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value == floor((float) $value)) {
            return sprintf('%.0f', (float) $value);
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
