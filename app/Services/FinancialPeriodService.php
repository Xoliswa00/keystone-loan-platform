<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\FinancialPeriod;
use App\Models\gl_accounts;
use App\Models\glmapping;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Financial Period Service
 *
 * Manages the full lifecycle of accounting periods:
 *  open → closing → closed → locked
 *  Plus year-end close (income/expense → retained earnings).
 *
 * The GLPostingService calls canPostToPeriod() before every GL entry.
 * Locked periods are immutable — no postings allowed.
 */
class FinancialPeriodService
{
    protected GLPostingService $glPosting;

    protected BadDebtProvisionService $provision;

    protected FundingFacilityService $facility;

    public function __construct(
        GLPostingService $glPosting,
        BadDebtProvisionService $provision,
        FundingFacilityService $facility
    ) {
        $this->glPosting = $glPosting;
        $this->provision = $provision;
        $this->facility = $facility;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Period guard — called by GLPostingService before every posting
    // ──────────────────────────────────────────────────────────────────────────

    public function canPostToPeriod(string $date): bool
    {
        $period = FinancialPeriod::forDate($date);

        if (! $period) {
            // Period doesn't exist yet → auto-create as open
            FinancialPeriod::ensure(Carbon::parse($date)->format('Y-m'));

            return true;
        }

        return $period->canPost();
    }

    public function assertCanPost(string $date): void
    {
        if (! $this->canPostToPeriod($date)) {
            $p = Carbon::parse($date)->format('F Y');
            throw new Exception("Period {$p} is locked — no GL postings are allowed. Contact your administrator.");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Open a new period
    // ──────────────────────────────────────────────────────────────────────────

    public function openPeriod(string $period): FinancialPeriod
    {
        return FinancialPeriod::firstOrCreate(
            ['period' => $period],
            [
                'fiscal_year' => (int) substr($period, 0, 4),
                'fiscal_month' => (int) substr($period, 5, 2),
                'is_year_end' => (int) substr($period, 5, 2) === 12,
                'status' => 'open',
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Start period-end process — moves to 'closing', runs pre-close steps
    // ──────────────────────────────────────────────────────────────────────────

    public function startClose(string $period, int $adminId): FinancialPeriod
    {
        $fp = FinancialPeriod::where('period', $period)->firstOrFail();

        if (! $fp->isOpen()) {
            throw new Exception("Period {$period} is already {$fp->status}.");
        }

        $fp->update(['status' => 'closing']);

        return $fp;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Run each pre-close step individually (called from UI one by one)
    // ──────────────────────────────────────────────────────────────────────────

    public function runProvisioning(FinancialPeriod $fp, int $adminId): void
    {
        $results = $this->provision->runMonthlyProvisioning($adminId);

        $fp->update([
            'provisioning_complete' => true,
            'notes' => ($fp->notes ?? '')."\nProvisioning: {$results['processed']} loans processed.",
        ]);
    }

    public function runFacilityInterest(FinancialPeriod $fp, int $adminId): void
    {
        $results = $this->facility->accrueAllActive($adminId);

        $fp->update([
            'facility_interest_accrued' => true,
            'notes' => ($fp->notes ?? '')."\nFacility interest: {$results['accrued']} accrued.",
        ]);
    }

    public function markBankReconComplete(FinancialPeriod $fp): void
    {
        // Verify at least one business bank batch is RECONCILED for this period
        $recon = DB::table('import_batches')
            ->where('source', 'business_bank')
            ->where('status', 'RECONCILED')
            ->where('meta', 'like', '%'.$fp->period.'%')
            ->exists();

        if (! $recon) {
            throw new Exception("No completed bank reconciliation found for {$fp->period}. Complete the bank reconciliation first.");
        }

        $fp->update(['bank_recon_complete' => true]);
    }

    /**
     * Alternative to markBankReconComplete() for a period with genuinely no
     * bank activity — a quiet month can never produce a RECONCILED
     * import_batches row, so the normal check can never be satisfied.
     * System-verified, not a blind admin button: refuses the override if it
     * finds any repayments, disbursements, or an uploaded bank statement
     * actually dated in this period, so it can't be used to wave through a
     * period that had real movement.
     */
    public function markBankReconNoActivity(FinancialPeriod $fp, int $adminId): void
    {
        $hasRepayments = DB::table('loan_repayments')
            ->whereRaw("DATE_FORMAT(payment_date, '%Y-%m') = ?", [$fp->period])
            ->exists();
        $hasDisbursements = DB::table('loan_disbursements')
            ->whereRaw("DATE_FORMAT(disbursement_date, '%Y-%m') = ?", [$fp->period])
            ->exists();
        $hasBankBatches = DB::table('import_batches')
            ->where('source', 'business_bank')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$fp->period])
            ->exists();

        if ($hasRepayments || $hasDisbursements || $hasBankBatches) {
            throw new Exception("Cannot confirm 'no activity' for {$fp->period} — the system found repayments, disbursements, or an uploaded bank statement for this period. Complete bank reconciliation normally.");
        }

        $fp->update([
            'bank_recon_complete' => true,
            'bank_recon_no_activity' => true,
            'notes' => ($fp->notes ?? '')."\nBank recon: no activity found for {$fp->period} — confirmed by admin #{$adminId} on ".now()->toDateString().'.',
        ]);
    }

    public function generateTrialBalance(FinancialPeriod $fp, int $adminId): array
    {
        $trialBalance = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->select('coa.account_code', 'coa.account_category', 'ga.current_balance')
            ->orderBy('coa.account_code')
            ->get()
            ->keyBy('account_code')
            ->map(fn ($r) => (float) $r->current_balance)
            ->toArray();

        // Calculate P&L for the period
        $incomeRows = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->join('glentries as ge', 'ge.account_id', '=', 'ga.id')
            ->join('glbatches as gb', 'gb.id', '=', 'ge.batch_id')
            ->where('coa.account_category', 'income')
            ->where('gb.posted_at', 'like', $fp->period.'%')
            ->selectRaw('SUM(ge.credit - ge.debit) as net')
            ->value('net') ?? 0;

        $expenseRows = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->join('glentries as ge', 'ge.account_id', '=', 'ga.id')
            ->join('glbatches as gb', 'gb.id', '=', 'ge.batch_id')
            ->where('coa.account_category', 'expense')
            ->where('gb.posted_at', 'like', $fp->period.'%')
            ->selectRaw('SUM(ge.debit - ge.credit) as net')
            ->value('net') ?? 0;

        $netProfit = round($incomeRows - $expenseRows, 2);

        $fp->update([
            'trial_balance_generated' => true,
            'gl_closing_balances' => $trialBalance,
            'period_income' => round($incomeRows, 2),
            'period_expenses' => round($expenseRows, 2),
            'period_net_profit' => $netProfit,
        ]);

        return [
            'trial_balance' => $trialBalance,
            'income' => round($incomeRows, 2),
            'expenses' => round($expenseRows, 2),
            'net_profit' => $netProfit,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Close period — all pre-close steps must be complete
    // ──────────────────────────────────────────────────────────────────────────

    public function closePeriod(FinancialPeriod $fp, int $adminId): FinancialPeriod
    {
        if (! in_array($fp->status, ['open', 'closing'])) {
            throw new Exception("Period {$fp->period} is already {$fp->status}.");
        }

        if (! $fp->checklistComplete()) {
            $missing = [];
            if (! $fp->provisioning_complete) {
                $missing[] = 'IFRS 9 Provisioning';
            }
            if (! $fp->facility_interest_accrued) {
                $missing[] = 'Facility Interest Accrual';
            }
            if (! $fp->bank_recon_complete) {
                $missing[] = 'Bank Reconciliation';
            }
            if (! $fp->trial_balance_generated) {
                $missing[] = 'Trial Balance';
            }
            throw new Exception('Pre-close checklist incomplete. Missing: '.implode(', ', $missing));
        }

        $fp->update([
            'status' => 'closed',
            'closed_by' => $adminId,
            'closed_at' => now(),
        ]);

        // If year-end, run the year-end close journal
        if ($fp->is_year_end) {
            $this->runYearEnd($fp, $adminId);
        }

        // Auto-open next period
        $next = Carbon::createFromFormat('Y-m', $fp->period)->addMonth()->format('Y-m');
        $this->openPeriod($next);

        Log::info("Period {$fp->period} closed by user #{$adminId}");

        return $fp->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Lock period — final sign-off; immutable after this
    // ──────────────────────────────────────────────────────────────────────────

    public function lockPeriod(FinancialPeriod $fp, int $adminId): FinancialPeriod
    {
        if ($fp->status !== 'closed') {
            throw new Exception("Only closed periods can be locked. Current status: {$fp->status}.");
        }

        $fp->update([
            'status' => 'locked',
            'locked_by' => $adminId,
            'locked_at' => now(),
        ]);

        return $fp->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Year-end close — transfer net profit/loss to retained earnings
    // ──────────────────────────────────────────────────────────────────────────

    public function runYearEnd(FinancialPeriod $fp, int $adminId): void
    {
        DB::transaction(function () use ($fp, $adminId) {

            $retainedEarningsGl = $this->resolveGl('retained_earnings_cr');
            $customer = \App\Models\Customer::first();

            if (! $retainedEarningsGl) {
                Log::warning('Year-end close: retained_earnings_cr GL mapping not found. Skipping year-end journal.');

                return;
            }

            $netProfit = (float) $fp->period_net_profit;
            if (abs($netProfit) < 0.01) {
                return; // nothing to transfer
            }

            $ref = 'YE-'.$fp->fiscal_year.'-CLOSE';

            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer?->id ?? 1,
                'source_type' => FinancialPeriod::class,
                'source_id' => $fp->id,
                'total_amount' => abs($netProfit),
                'status' => 'approved',
                'created_by' => $adminId,
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            $entries = [];

            if ($netProfit > 0) {
                // Net profit: Dr Income accounts, Cr Retained Earnings
                // (clear income accounts to zero)
                $incomeAccounts = DB::table('gl_accounts as ga')
                    ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
                    ->where('coa.account_category', 'income')
                    ->where('ga.current_balance', '!=', 0)
                    ->get();

                foreach ($incomeAccounts as $acc) {
                    $bal = abs((float) $acc->current_balance);
                    if ($bal < 0.01) {
                        continue;
                    }
                    $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $acc->id, 'entry_type' => 'debit', 'amount' => $bal, 'description' => "Year-end close {$fp->fiscal_year} — clear income", 'created_at' => now(), 'updated_at' => now()];
                }

                // Clear expense accounts
                $expenseAccounts = DB::table('gl_accounts as ga')
                    ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
                    ->where('coa.account_category', 'expense')
                    ->where('ga.current_balance', '!=', 0)
                    ->get();

                foreach ($expenseAccounts as $acc) {
                    $bal = abs((float) $acc->current_balance);
                    if ($bal < 0.01) {
                        continue;
                    }
                    $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $acc->id, 'entry_type' => 'credit', 'amount' => $bal, 'description' => "Year-end close {$fp->fiscal_year} — clear expenses", 'created_at' => now(), 'updated_at' => now()];
                }

                // Net to retained earnings
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $retainedEarningsGl->id, 'entry_type' => 'credit', 'amount' => $netProfit, 'description' => "Year-end {$fp->fiscal_year} net profit → retained earnings", 'created_at' => now(), 'updated_at' => now()];

            } else {
                // Net loss: Dr Retained Earnings / Cr Income+Expenses clearing
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $retainedEarningsGl->id, 'entry_type' => 'debit', 'amount' => abs($netProfit), 'description' => "Year-end {$fp->fiscal_year} net loss from retained earnings", 'created_at' => now(), 'updated_at' => now()];
            }

            if (! empty($entries)) {
                arbatch_entries::insert($entries);
                try {
                    $this->glPosting->postArBatch($arBatch, $adminId);
                    $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);
                } catch (Exception $e) {
                    Log::warning('Year-end GL posting failed: '.$e->getMessage());
                }
            }

            $fp->update([
                'year_end_gl_batch_ref' => $ref,
                'year_end_complete' => true,
            ]);

            Log::info("Year-end close for {$fp->fiscal_year} complete. Net: R{$netProfit}");
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function resolveGl(string $key): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            return null;
        }

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $mapping->account_code))->first();
    }
}
