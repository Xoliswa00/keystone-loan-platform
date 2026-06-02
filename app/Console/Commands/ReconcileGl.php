<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileGl extends Command
{
    protected $signature   = 'keystone:reconcile-gl {--fix : Log discrepancies only (default) or flag them}';
    protected $description = 'Check that the loan ledger (remaining_balance totals) reconciles to the GL Loans Receivable account balance.';

    public function handle(): int
    {
        $this->info('Running GL reconciliation checks...');
        $issues = 0;

        // ── 1. Double-entry check — every GL batch must balance ───────────────
        $unbalanced = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->select(
                'gb.id',
                'gb.reference',
                DB::raw('ROUND(SUM(ge.debit),2)  as total_debit'),
                DB::raw('ROUND(SUM(ge.credit),2) as total_credit'),
                DB::raw('ROUND(ABS(SUM(ge.debit) - SUM(ge.credit)),2) as variance')
            )
            ->groupBy('gb.id', 'gb.reference')
            ->having('variance', '>', 0.01)
            ->get();

        if ($unbalanced->isNotEmpty()) {
            $this->error("UNBALANCED GL BATCHES: {$unbalanced->count()}");
            foreach ($unbalanced as $b) {
                $this->warn("  Batch #{$b->id} ({$b->reference}): Dr {$b->total_debit} / Cr {$b->total_credit} — variance {$b->variance}");
                Log::error("GL batch #{$b->id} unbalanced", (array) $b);
            }
            $issues += $unbalanced->count();
        } else {
            $this->info('  [OK] All GL batches are balanced.');
        }

        // ── 2. Loan ledger vs GL Loans Receivable ─────────────────────────────
        $loanLedgerTotal = DB::table('loans')
            ->whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->sum('remaining_balance');

        // GL account 1200 = Loans Receivable
        $glLoansReceivable = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
            ->where('coa.account_code', '1200')
            ->value('ga.current_balance') ?? 0;

        $lrVariance = round(abs($loanLedgerTotal - $glLoansReceivable), 2);

        if ($lrVariance > 1.00) {
            $this->error("LOAN RECEIVABLE VARIANCE: R{$lrVariance}");
            $this->warn("  Loan ledger total : R" . number_format($loanLedgerTotal, 2));
            $this->warn("  GL account 1200   : R" . number_format($glLoansReceivable, 2));
            Log::error("GL reconciliation: Loans Receivable variance R{$lrVariance}");
            $issues++;
        } else {
            $this->info('  [OK] Loan ledger matches GL Loans Receivable.');
        }

        // ── 3. Deferred income check ───────────────────────────────────────────
        $deferredInterestLedger = DB::table('loans')
            ->where('status', 'disbursed')
            ->sum('deferred_interest');

        $deferredIntGl = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
            ->where('coa.account_code', '2100')
            ->value('ga.current_balance') ?? 0;

        $diVariance = round(abs($deferredInterestLedger - $deferredIntGl), 2);

        if ($diVariance > 1.00) {
            $this->warn("DEFERRED INTEREST VARIANCE: R{$diVariance}");
            Log::warning("GL reconciliation: Deferred interest variance R{$diVariance}");
            $issues++;
        } else {
            $this->info('  [OK] Deferred interest matches GL account 2100.');
        }

        // ── Summary ────────────────────────────────────────────────────────────
        if ($issues === 0) {
            $this->info('GL reconciliation passed — no issues found.');
        } else {
            $this->error("GL reconciliation found {$issues} issue(s). Review logs.");
        }

        return $issues > 0 ? self::FAILURE : self::SUCCESS;
    }
}
