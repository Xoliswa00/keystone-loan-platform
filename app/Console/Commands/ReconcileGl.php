<?php

namespace App\Console\Commands;

use App\Services\GlReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileGl extends Command
{
    protected $signature = 'keystone:reconcile-gl';

    protected $description = 'Check that the loan ledger (remaining_balance totals) reconciles to the GL Loans Receivable account balance.';

    public function handle(GlReconciliationService $recon): int
    {
        $this->info('Running GL reconciliation checks...');
        $issues = 0;

        // ── 1. Double-entry check — every GL batch must balance ───────────────
        $unbalanced = $recon->unbalancedBatches();

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
        $lr = $recon->loanReceivableCheck();

        if ($lr['variance'] > 1.00) {
            $this->error("LOAN RECEIVABLE VARIANCE: R{$lr['variance']}");
            $this->warn('  Loan ledger total : R'.number_format($lr['ledger_total'], 2));
            $this->warn('  GL account 1200   : R'.number_format($lr['gl_total'], 2));
            Log::error("GL reconciliation: Loans Receivable variance R{$lr['variance']}");
            $issues++;
        } else {
            $this->info('  [OK] Loan ledger matches GL Loans Receivable.');
        }

        // ── 3. Deferred income check ───────────────────────────────────────────
        $di = $recon->deferredInterestCheck();

        if ($di['variance'] > 1.00) {
            $this->warn("DEFERRED INTEREST VARIANCE: R{$di['variance']}");
            Log::warning("GL reconciliation: Deferred interest variance R{$di['variance']}");
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
