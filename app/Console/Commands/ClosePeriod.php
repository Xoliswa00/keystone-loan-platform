<?php

namespace App\Console\Commands;

use App\Services\BadDebtProvisionService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClosePeriod extends Command
{
    protected $signature   = 'keystone:close-period {period : Period in YYYY-MM format, e.g. 2026-05} {--admin-id= : User ID}';
    protected $description = 'Run month-end close: provision, GL balance check, lock period marker.';

    public function handle(BadDebtProvisionService $provision): int
    {
        $period  = $this->argument('period');
        $adminId = (int) ($this->option('admin-id') ?? User::where('rule_id', 2)->value('id') ?? 1);

        try {
            $date = Carbon::createFromFormat('Y-m', $period);
        } catch (\Exception $e) {
            $this->error("Invalid period format. Use YYYY-MM.");
            return self::FAILURE;
        }

        $this->info("Starting month-end close for {$period}...");

        // ── 1. Run IFRS 9 provisioning ─────────────────────────────────────────
        $this->info('Step 1: IFRS 9 provisioning...');
        $results = $provision->runMonthlyProvisioning($adminId);
        $this->info("  Provisioned: {$results['processed']} loans");

        if (!empty($results['errors'])) {
            $this->warn('  Provision errors: ' . implode(', ', $results['errors']));
        }

        // ── 2. GL reconciliation ──────────────────────────────────────────────
        $this->info('Step 2: GL reconciliation check...');
        $this->call('keystone:reconcile-gl');

        // ── 3. Mark period closed in a period_closes table (create if needed) ──
        $this->info('Step 3: Recording period close...');

        DB::table('period_closes')->insertOrIgnore([
            'period'      => $period,
            'closed_by'   => $adminId,
            'closed_at'   => now(),
            'notes'       => "Month-end close for {$period} completed via artisan command.",
        ]);

        // ── 4. Summary ────────────────────────────────────────────────────────
        $totalIncome = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->whereRaw("DATE_FORMAT(rs.paid_at, '%Y-%m') = ?", [$period])
            ->sum(DB::raw('rs.interest_amount + rs.fee_amount'));

        $totalCollected = DB::table('repayment_schedules as rs')
            ->where('rs.status', 'paid')
            ->whereRaw("DATE_FORMAT(rs.paid_at, '%Y-%m') = ?", [$period])
            ->sum('rs.emi_amount');

        $this->table(
            ['Metric', 'Amount'],
            [
                ['Total Collected',   'R ' . number_format($totalCollected, 2)],
                ['Income Recognised', 'R ' . number_format($totalIncome, 2)],
                ['Provisions Run',    $results['processed']],
            ]
        );

        $this->info("Period {$period} closed successfully.");
        return self::SUCCESS;
    }
}
