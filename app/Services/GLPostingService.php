<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\gl_accounts;
use App\Models\glbatch;
use App\Models\glentry;
use App\Models\glpost;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GLPostingService
{
    /**
     * Account type constants — drives debit/credit balance direction.
     * Assets and Expenses: debit increases, credit decreases.
     * Liabilities, Income, Equity: credit increases, debit decreases.
     */
    const NORMAL_DEBIT_TYPES = ['asset', 'expense'];

    const NORMAL_CREDIT_TYPES = ['liability', 'income', 'equity', 'deferred_income'];

    /**
     * Post an approved AR batch fully into the General Ledger.
     * Flow: AR Batch → GL Batch → GL Entries → GL Posts → Account Balances
     */
    public function postArBatch(arbatch $arBatch, int $userId): glbatch
    {
        return DB::transaction(function () use ($arBatch, $userId) {

            if ($arBatch->status === 'posted') {
                throw new Exception("AR batch #{$arBatch->id} already posted.");
            }

            // ── Period guard ──────────────────────────────────────────────────
            // Check the posting date against the financial period status.
            // Locked periods reject all postings (closed periods still accept adjustments).
            $postDate = $arBatch->approved_at ?? now();
            $period = \App\Models\FinancialPeriod::forDate($postDate->toDateString());

            if ($period && $period->isLocked()) {
                throw new Exception(
                    "Period {$period->displayLabel()} is locked — no GL postings allowed. ".
                    'Contact your finance administrator.'
                );
            }

            // Auto-create the period as open if it doesn't exist yet
            if (! $period) {
                \App\Models\FinancialPeriod::ensure($postDate->format('Y-m'));
            }

            $arBatch->loadMissing('entries');

            if ($arBatch->entries->isEmpty()) {
                throw new Exception("AR batch #{$arBatch->id} has no entries.");
            }

            // ── 1. GL Batch header ──────────────────────────────────────────────
            $glBatch = glbatch::create([
                'reference' => 'GLB-'.now()->format('YmdHis').'-'.$arBatch->id,
                'source_type' => $arBatch->source_type,
                'source_id' => $arBatch->source_id,
                'status' => 'posted',
                'created_by' => $userId,
                'posted_at' => now(),
            ]);

            $totalDebit = 0;
            $totalCredit = 0;

            // ── 2. Process each entry ───────────────────────────────────────────
            foreach ($arBatch->entries as $arEntry) {

                $debit = $arEntry->entry_type === 'debit' ? (float) $arEntry->amount : 0;
                $credit = $arEntry->entry_type === 'credit' ? (float) $arEntry->amount : 0;

                $totalDebit += $debit;
                $totalCredit += $credit;

                // GL Entry (journal line)
                $glEntry = glentry::create([
                    'batch_id' => $glBatch->id,
                    'account_id' => $arEntry->gl_account_id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $arEntry->description,
                ]);

                // Lock account row before balance update
                $account = gl_accounts::lockForUpdate()->findOrFail($arEntry->gl_account_id);

                // GL Post (the authoritative ledger line)
                glpost::create([
                    'entry_id' => $glEntry->id,
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'post_date' => now()->toDateString(),
                    'reference' => $glBatch->reference,
                    'module' => $glBatch->source_type,
                ]);

                // Account balance — direction depends on account type
                $account->current_balance = $this->updatedBalance(
                    (float) $account->current_balance,
                    $debit,
                    $credit,
                    $account->account_type ?? $this->resolveAccountType($account)
                );

                $account->save();
            }

            // ── 3. Enforce double-entry rule ────────────────────────────────────
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new Exception(
                    "GL batch not balanced — debit R{$totalDebit} ≠ credit R{$totalCredit}"
                );
            }

            // ── 4. Stamp AR batch ───────────────────────────────────────────────
            $arBatch->update([
                'status' => 'posted',
                'posted_to_gl' => true,
                'posted_at' => now(),
            ]);

            Log::info('AR batch posted to GL', [
                'ar_batch_id' => $arBatch->id,
                'gl_batch_id' => $glBatch->id,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            return $glBatch;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Balance direction helper
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Apply debit/credit to an account balance using correct accounting rules.
     *
     * Normal balance for account type:
     *   Asset / Expense   → debit normal → balance += debit, -= credit
     *   Liability / Income / Equity / Deferred → credit normal → balance += credit, -= debit
     */
    protected function updatedBalance(float $current, float $debit, float $credit, string $type): float
    {
        $type = strtolower($type);

        if (in_array($type, self::NORMAL_DEBIT_TYPES)) {
            return $current + $debit - $credit;
        }

        // Liability, income, equity, deferred — credit normal
        return $current + $credit - $debit;
    }

    /**
     * Resolve account type from the chart_of_accounts if not stored on gl_accounts.
     */
    protected function resolveAccountType(gl_accounts $account): string
    {
        if ($account->chartOfAccount) {
            return strtolower($account->chartOfAccount->account_type ?? 'asset');
        }

        return 'asset'; // safe default — will debit-increase
    }
}
