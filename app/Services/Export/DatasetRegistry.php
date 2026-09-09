<?php

namespace App\Services\Export;

use Illuminate\Database\Eloquent\Builder;

/**
 * The datasets an export profile can draw from, and the ONLY column sources it
 * may select. Backed by config/exports.php. This is the security boundary of
 * the "pick columns, no expressions" design — anything not listed here is
 * rejected at profile save time and again at run time.
 */
class DatasetRegistry
{
    /** @return array<string,array> */
    public function all(): array
    {
        return (array) config('exports.datasets', []);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** @return array{model:string,label:string,filterable:array,columns:array} */
    public function get(string $key): array
    {
        $all = $this->all();

        if (! isset($all[$key])) {
            throw new \InvalidArgumentException("Unknown dataset '{$key}'.");
        }

        return $all[$key];
    }

    public function model(string $key): string
    {
        return $this->get($key)['model'];
    }

    /** @return array<string,array{type:string,pii:bool,internal:bool}> */
    public function columns(string $key): array
    {
        return $this->get($key)['columns'] ?? [];
    }

    /** @return string[] */
    public function columnKeys(string $key): array
    {
        return array_keys($this->columns($key));
    }

    /** @return string[] */
    public function filterable(string $key): array
    {
        return $this->get($key)['filterable'] ?? [];
    }

    public function defaultFormat(string $key, string $source): string
    {
        return $this->columns($key)[$source]['type'] ?? 'string';
    }

    public function query(string $key): Builder
    {
        $model = $this->model($key);

        return $model::query();
    }

    /**
     * Reject a column that must not reach this recipient:
     *  - internal accounting/risk figures never leave the building
     *  - PII may only go to a recipient of kind 'bureau'
     *
     * @throws \InvalidArgumentException
     */
    public function assertColumnAllowed(string $dataset, string $source, string $recipientKind): void
    {
        $cols = $this->columns($dataset);

        if (! isset($cols[$source])) {
            throw new \InvalidArgumentException("Column '{$source}' is not part of dataset '{$dataset}'.");
        }

        $meta = $cols[$source];

        if (($meta['internal'] ?? false) && $recipientKind !== 'internal') {
            throw new \InvalidArgumentException("Column '{$source}' is internal-only and cannot be sent to an external recipient.");
        }

        if (($meta['pii'] ?? false) && $recipientKind !== 'bureau') {
            throw new \InvalidArgumentException("Column '{$source}' is personal information and may only be sent to a credit bureau recipient.");
        }
    }
}
