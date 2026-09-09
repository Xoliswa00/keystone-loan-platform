<?php

namespace App\Console\Commands;

use App\Services\Reporting\AccountSnapshotService;
use Illuminate\Console\Command;

class BuildAccountSnapshots extends Command
{
    protected $signature = 'keystone:build-account-snapshots
        {period? : Reporting month YYYY-MM (defaults to the previous calendar month)}
        {--generation= : Force a specific generation number (advanced; normally auto-resolved)}';

    protected $description = 'Materialise the monthly reporting_account_snapshots table used by the outbound data-export engine.';

    public function handle(AccountSnapshotService $service): int
    {
        if (! config('exports.snapshots_enabled')) {
            $this->warn('Account snapshots are disabled (DATA_EXPORTS_SNAPSHOTS_ENABLED=false). Nothing built.');

            return self::SUCCESS;
        }

        $period = $this->argument('period') ?: now()->subMonthNoOverflow()->format('Y-m');
        $generation = $this->option('generation') !== null ? (int) $this->option('generation') : null;

        try {
            $result = $service->buildForPeriod($period, $generation);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Built {$result['rows']} snapshot rows for {$result['period']} (generation {$result['generation']}).");

        return self::SUCCESS;
    }
}
