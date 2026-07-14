<?php

namespace App\Console\Commands;

use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\FinancialPeriodService;
use Exception;
use Illuminate\Console\Command;

class ClosePeriod extends Command
{
    protected $signature = 'keystone:close-period {period : Period in YYYY-MM format, e.g. 2026-05} {--admin-id= : User ID}';

    protected $description = 'Run month-end close via the same checklist/gate the admin UI enforces (provisioning, facility interest, bank recon, trial balance).';

    public function handle(FinancialPeriodService $service): int
    {
        $period = $this->argument('period');

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Invalid period format. Use YYYY-MM.');

            return self::FAILURE;
        }

        $adminId = (int) ($this->option('admin-id') ?? User::where('rule_id', 2)->value('id') ?? 1);

        $fp = FinancialPeriod::ensure($period);

        try {
            if ($fp->isOpen()) {
                $this->info('Starting close...');
                $service->startClose($fp->period, $adminId);
                $fp->refresh();
            }

            if (! $fp->provisioning_complete) {
                $this->info('Running IFRS 9 provisioning...');
                $service->runProvisioning($fp, $adminId);
                $fp->refresh();
            }

            if (! $fp->facility_interest_accrued) {
                $this->info('Accruing facility interest...');
                $service->runFacilityInterest($fp, $adminId);
                $fp->refresh();
            }

            if (! $fp->bank_recon_complete) {
                $this->error("Bank reconciliation not complete for {$period}. Reconcile via the admin UI, or if the period genuinely had no bank activity, use the 'No Activity' button on the period page — this command won't attempt that override automatically.");

                return self::FAILURE;
            }

            if (! $fp->trial_balance_generated) {
                $this->info('Generating trial balance...');
                $service->generateTrialBalance($fp, $adminId);
                $fp->refresh();
            }

            $service->closePeriod($fp, $adminId);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Period {$period} closed successfully.");

        return self::SUCCESS;
    }
}
