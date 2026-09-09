<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Renders one snapshot value into its string form for an export file.
 * Presentational only — no expressions, no cross-field logic. The set of
 * tokens here is the whole vocabulary an export profile column may use.
 */
class ColumnFormatter
{
    public static function apply(mixed $value, ?string $format): string
    {
        $format = $format ?: 'string';

        if ($value === null || $value === '') {
            return '';
        }

        if (str_starts_with($format, 'date:')) {
            try {
                return Carbon::parse($value)->format(substr($format, 5));
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        // map:<name> — translate via a config-defined lookup table
        // (config/exports.php 'maps'). Still pure: a fixed dictionary, no logic.
        if (str_starts_with($format, 'map:')) {
            $table = (array) config('exports.maps.'.substr($format, 4), []);

            return (string) ($table[(string) $value] ?? $value);
        }

        return match ($format) {
            'money' => number_format((float) $value, 2, '.', ''),
            'integer' => (string) (int) round((float) $value),
            'upper' => mb_strtoupper((string) $value),
            default => (string) $value,
        };
    }
}
