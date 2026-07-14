<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FundingFacilityService;
use Illuminate\Console\Command;

class AccrueFacilityInterest extends Command
{
    protected $signature = 'keystone:accrue-facility-interest {--admin-id= : User ID (defaults to first admin)}';

    protected $description = 'Run monthly interest accrual on all active funding facilities. Schedule on last business day of month.';

    public function handle(FundingFacilityService $service): int
    {
        $adminId = (int) ($this->option('admin-id') ?? User::where('rule_id', 2)->value('id') ?? 1);

        $this->info("Running facility interest accrual (admin_id={$adminId})...");

        $results = $service->accrueAllActive($adminId);

        $this->info("  Accrued : {$results['accrued']}");
        $this->info("  Skipped : {$results['skipped']}");

        if (! empty($results['errors'])) {
            foreach ($results['errors'] as $err) {
                $this->warn("  {$err}");
            }
        }

        return self::SUCCESS;
    }
}
