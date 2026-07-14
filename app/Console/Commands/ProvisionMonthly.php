<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadDebtProvisionService;
use Illuminate\Console\Command;

class ProvisionMonthly extends Command
{
    protected $signature = 'keystone:provision-monthly {--admin-id= : User ID to log under (defaults to first admin)}';

    protected $description = 'Run IFRS 9 ECL provisioning for all active loans. Schedule monthly on the last business day.';

    public function handle(BadDebtProvisionService $service): int
    {
        $adminId = $this->option('admin-id')
            ?? User::where('rule_id', 2)->value('id')
            ?? 1;

        $this->info("Running monthly IFRS 9 provisioning (admin_id={$adminId})...");

        $results = $service->runMonthlyProvisioning((int) $adminId);

        $this->info("  Processed : {$results['processed']}");
        $this->info("  Skipped   : {$results['skipped']}");

        if (! empty($results['errors'])) {
            $this->warn('  Errors    : '.count($results['errors']));
            foreach ($results['errors'] as $err) {
                $this->error("    {$err}");
            }
        }

        $this->info('Provisioning complete.');

        return self::SUCCESS;
    }
}
