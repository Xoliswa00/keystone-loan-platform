<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The 3 GL reconciliation checks, shared by `keystone:reconcile-gl` and the
 * admin/gl-recon web page — previously the web page only reproduced check
 * #1 (unbalanced batches) and silently omitted #2/#3, so this is the single
 * source of truth both now call.
 */
class GlReconciliationService
{
    /**
     * Check 1: every GL batch must balance (debits = credits).
     * Optionally date-filtered on gb.posted_at (the web page's date range);
     * the console command calls this with no range — all unbalanced
     * batches, ever.
     */
    public function unbalancedBatches(?string $from = null, ?string $to = null)
    {
        $query = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->select(
                'gb.id',
                'gb.reference',
                'gb.source_type',
                'gb.posted_at',
                DB::raw('ROUND(SUM(ge.debit),2)  as total_debit'),
                DB::raw('ROUND(SUM(ge.credit),2) as total_credit'),
                DB::raw('ROUND(ABS(SUM(ge.debit) - SUM(ge.credit)),2) as variance')
            );

        if ($from && $to) {
            $query->whereBetween('gb.posted_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }

        return $query->groupBy('gb.id', 'gb.reference', 'gb.source_type', 'gb.posted_at')
            ->having('variance', '>', 0.01)
            ->orderByDesc('gb.posted_at')
            ->get();
    }

    /**
     * Check 2: loan ledger (remaining_balance totals) vs GL account 1200
     * (Loans Receivable) — point-in-time snapshot, not period-bound.
     */
    public function loanReceivableCheck(): array
    {
        $ledgerTotal = DB::table('loans')
            ->whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->sum('remaining_balance');

        $glTotal = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->where('coa.account_code', '1200')
            ->value('ga.current_balance') ?? 0;

        return [
            'ledger_total' => (float) $ledgerTotal,
            'gl_total' => (float) $glTotal,
            'variance' => round(abs($ledgerTotal - $glTotal), 2),
        ];
    }

    /**
     * Check 3: deferred interest (multi-month loans not yet recognised) vs
     * GL account 2100 — point-in-time snapshot, not period-bound.
     */
    public function deferredInterestCheck(): array
    {
        $ledgerTotal = DB::table('loans')
            ->where('status', 'disbursed')
            ->sum('deferred_interest');

        $glTotal = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_of_account_id', '=', 'coa.id')
            ->where('coa.account_code', '2100')
            ->value('ga.current_balance') ?? 0;

        return [
            'ledger_total' => (float) $ledgerTotal,
            'gl_total' => (float) $glTotal,
            'variance' => round(abs($ledgerTotal - $glTotal), 2),
        ];
    }
}
