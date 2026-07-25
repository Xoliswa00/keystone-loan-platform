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

    const NORMAL_CREDIT_TYPES = ['liability', 'income', 'equity'];

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

                // Account balance — direction depends on account category.
                // gl_accounts has no account_type column of its own (it's
                // always null), so this always resolved via
                // resolveAccountCategory() anyway — the `??` implied a fallback
                // that never existed.
                $account->current_balance = $this->updatedBalance(
                    (float) $account->current_balance,
                    $debit,
                    $credit,
                    $this->resolveBalanceDirection($account)
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
     * Resolve the account's category (asset/liability/equity/income/expense)
     * from chart_of_accounts — this drives debit/credit balance direction
     * and must be account_category, not account_type. account_type holds
     * granular sub-types ('bank', 'receivable', 'contra_asset', 'vat',
     * 'deferred_income', 'payable', 'retained_earnings', ...) — none of
     * which is ever the literal string 'asset', so reading it here meant
     * NORMAL_DEBIT_TYPES (['asset', 'expense']) never matched a single real
     * asset account. Every asset account's balance was being updated with
     * the credit-normal formula instead of the debit-normal one — credits
     * were increasing Cash instead of decreasing it, and debits were
     * decreasing Loans Receivable instead of increasing it.
     */
    protected function resolveAccountCategory(gl_accounts $account): string
    {
        if ($account->chartOfAccount) {
            return strtolower($account->chartOfAccount->account_category ?? 'asset');
        }

        return 'asset'; // safe default — will debit-increase
    }

    /**
     * Balance direction is normally driven by account_category, but a
     * contra-asset (e.g. 1240 "Allowance for Credit Losses") sits under the
     * 'asset' category for balance-sheet grouping while carrying the
     * opposite (credit-normal) balance of a real asset — provisioning
     * credits it as the book grows, and that credit must increase, not
     * decrease, its balance. account_type is the only place that
     * distinction is recorded, so it must override category here.
     */
    public function resolveBalanceDirection(gl_accounts $account): string
    {
        if ($account->chartOfAccount && strtolower($account->chartOfAccount->account_type ?? '') === 'contra_asset') {
            return 'liability'; // credit-normal, same branch as liability/income/equity
        }

        return $this->resolveAccountCategory($account);
    }
}
