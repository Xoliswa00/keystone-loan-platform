<?php

namespace App\Console\Commands;

use App\Services\NcrExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NcrExport extends Command
{
    protected $signature = 'keystone:ncr-export {quarter : Quarter in YYYY-QN format, e.g. 2026-Q1}';

    protected $description = 'Generate NCR quarterly return CSV extract for submission to the National Credit Regulator.';

    public function handle(NcrExportService $export): int
    {
        $quarter = $this->argument('quarter');

        try {
            $loans = $export->loansForQuarter($quarter);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Generating NCR return for {$quarter}...");

        if ($loans->isEmpty()) {
            $this->warn("No disbursements found for {$quarter}.");

            return self::SUCCESS;
        }

        $filename = "ncr_return_{$quarter}_".now()->format('Ymd_His').'.csv';
        $path = "ncr_exports/{$filename}";

        Storage::disk('local')->put($path, $export->toCsv($loans));

        $this->info("NCR export complete: {$loans->count()} records");
        $this->info("File saved: storage/app/{$path}");

        return self::SUCCESS;
    }
}
