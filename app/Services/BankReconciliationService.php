<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\gl_accounts;
use App\Models\glmapping;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bank Reconciliation Service
 *
 * Every bank statement line must be allocated before reconciliation closes.
 *
 * Full match categories and GL treatment:
 *
 *  nu_pay_collection  → matched, no new GL (already posted via NuPayService)
 *  loan_disbursement  → matched, no new GL (already posted via DisbursementService)
 *  facility_drawdown  → matched, no new GL (already posted via FundingFacilityService)
 *  facility_repayment → matched, no new GL (already posted via FundingFacilityService)
 *  recovery_payment   → matched, no new GL (already posted via DebtRecoveryController)
 *  inter_account      → Dr/Cr between own bank accounts (no P&L impact)
 *
 *  expense            → NEW GL entry required:
 *                       Dr Expense Account (5xxx) / Cr Bank (1100)
 *                       This is how operating costs hit the P&L.
 *
 *  other              → manual description required, GL entry optional
 *
 * Reconciliation is COMPLETE when:
 *  1. All lines have match_status = 'matched'
 *  2. GL Bank account balance = Statement closing balance
 */
class BankReconciliationService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Allocate a single bank line to a GL account (expense or other)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  int  $lineId  bank_statement_lines.id
     * @param  string  $category  match_category enum value
     * @param  int  $glAccountId  chart_of_accounts.id to debit (for expenses) or credit
     * @param  string  $description  Human-readable description for the GL entry
     * @param  float|null  $overrideAmount  If null, uses the line's debit_amount or credit_amount
     */
    public function allocateLine(
        int $lineId,
        string $category,
        int $glAccountId,
        string $description,
        int $userId,
        ?float $overrideAmount = null
    ): void {

        DB::transaction(function () use ($lineId, $category, $glAccountId, $description, $userId, $overrideAmount) {

            $line = DB::table('bank_statement_lines')->lockForUpdate()->where('id', $lineId)->first();

            if (! $line) {
                throw new Exception("Bank statement line #{$lineId} not found.");
            }

            if ($line->match_status === 'matched') {
                throw new Exception("Line #{$lineId} is already matched.");
            }

            $amount = $overrideAmount ?? max($line->debit_amount, $line->credit_amount);

            if ($amount <= 0) {
                throw new Exception('Line has zero amount — cannot allocate.');
            }

            $isDebit = $line->debit_amount > 0; // money OUT of bank

            $glBatchRef = null;

            // For expense, inter-account, and other: post a GL entry
            if (in_array($category, ['expense', 'inter_account', 'other'])) {
                $glBatchRef = $this->postAllocationGl(
                    $lineId, $glAccountId, $amount, $isDebit,
                    $description, $line->import_batch_id, $userId
                );
            }

            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'match_status' => 'matched',
                'match_category' => $category,
                'allocated_gl_account_id' => $glAccountId,
                'allocation_description' => $description,
                'allocation_gl_batch_ref' => $glBatchRef,
                'allocation_posted' => $glBatchRef !== null,
                'allocated_by' => $userId,
                'allocated_at' => now(),
                'match_note' => $description,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Unallocate a previously matched line — the "undo" for a mistaken allocation.
    // If a GL batch was posted (expense/inter_account/other), reverses it with an
    // offsetting entry rather than deleting it, preserving the audit trail.
    // ──────────────────────────────────────────────────────────────────────────

    public function unallocateLine(int $lineId, int $userId): void
    {
        DB::transaction(function () use ($lineId, $userId) {
            $line = DB::table('bank_statement_lines')->lockForUpdate()->where('id', $lineId)->first();

            if (! $line) {
                throw new Exception("Bank statement line #{$lineId} not found.");
            }

            if ($line->match_status !== 'matched') {
                throw new Exception("Line #{$lineId} is not currently matched.");
            }

            $batch = DB::table('import_batches')->find($line->import_batch_id);
            if ($batch && $batch->status === 'RECONCILED') {
                throw new Exception('Cannot unallocate — this batch is already reconciled and the period is locked.');
            }

            if ($line->allocation_posted && $line->allocation_gl_batch_ref) {
                $this->reverseAllocationGl($line, $userId);
            }

            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'match_status' => 'unmatched',
                'match_category' => null,
                'allocated_gl_account_id' => null,
                'allocation_description' => null,
                'allocation_gl_batch_ref' => null,
                'allocation_posted' => false,
                'allocated_by' => null,
                'allocated_at' => null,
                'match_note' => 'Unallocated by user #'.$userId.' on '.now()->toDateTimeString(),
            ]);
        });
    }

    protected function reverseAllocationGl($line, int $userId): void
    {
        $original = arbatch::where('reference', $line->allocation_gl_batch_ref)->first();
        if (! $original) {
            return;
        }

        $originalEntries = arbatch_entries::where('arbatch_id', $original->id)->get();
        if ($originalEntries->isEmpty()) {
            return;
        }

        $reversalRef = $line->allocation_gl_batch_ref.'-REV';

        $reversalBatch = arbatch::create([
            'reference' => $reversalRef,
            'customer_id' => $original->customer_id,
            'source_type' => 'BankStatementLine',
            'source_id' => $line->id,
            'total_amount' => $original->total_amount,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $reversedEntries = $originalEntries->map(fn ($e) => [
            'arbatch_id' => $reversalBatch->id,
            'gl_account_id' => $e->gl_account_id,
            // Swap debit/credit to offset the original entry
            'entry_type' => $e->entry_type === 'debit' ? 'credit' : 'debit',
            'amount' => $e->amount,
            'description' => 'Reversal: '.$e->description,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        arbatch_entries::insert($reversedEntries);
        $this->glPosting->postArBatch($reversalBatch, $userId);
        $reversalBatch->update(['posted_to_gl' => true, 'status' => 'posted']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk allocate — same GL account for multiple lines (e.g. batch of bank charges)
    // ──────────────────────────────────────────────────────────────────────────

    public function bulkAllocate(
        array $lineIds,
        string $category,
        int $glAccountId,
        string $description,
        int $userId
    ): array {

        $results = ['allocated' => 0, 'errors' => []];

        foreach ($lineIds as $lineId) {
            try {
                $this->allocateLine((int) $lineId, $category, $glAccountId, $description, $userId);
                $results['allocated']++;
            } catch (Exception $e) {
                $results['errors'][] = "Line #{$lineId}: ".$e->getMessage();
                Log::warning("Bulk allocation failed for line #{$lineId}: ".$e->getMessage());
            }
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Get full reconciliation status for a batch
    // ──────────────────────────────────────────────────────────────────────────

    public function getReconciliationStatus(int $batchId, ?float $statementOpeningBalance = null, ?float $statementClosingBalance = null): array
    {
        $lines = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batchId)
            ->select(
                DB::raw('COUNT(*) as total_lines'),
                DB::raw("SUM(CASE WHEN match_status='matched' THEN 1 ELSE 0 END) as matched_lines"),
                DB::raw("SUM(CASE WHEN match_status='unmatched' THEN 1 ELSE 0 END) as unmatched_lines"),
                DB::raw("SUM(CASE WHEN match_status='exception' THEN 1 ELSE 0 END) as exception_lines"),
                DB::raw('SUM(credit_amount) as total_credits'),
                DB::raw('SUM(debit_amount) as total_debits'),
                DB::raw("SUM(CASE WHEN match_status='matched' THEN credit_amount ELSE 0 END) as matched_credits"),
                DB::raw("SUM(CASE WHEN match_status='matched' THEN debit_amount ELSE 0 END) as matched_debits"),
                DB::raw("SUM(CASE WHEN match_status!='matched' THEN credit_amount + debit_amount ELSE 0 END) as unmatched_amount")
            )
            ->first();

        $allMatched = $lines->unmatched_lines == 0 && $lines->exception_lines == 0;

        // Breakdown by category
        $byCategory = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batchId)
            ->whereNotNull('match_category')
            ->select('match_category', DB::raw('COUNT(*) as count'), DB::raw('SUM(credit_amount + debit_amount) as total'))
            ->groupBy('match_category')
            ->get()
            ->keyBy('match_category');

        // GL bank account balance — compare to statement closing balance
        $glBankBalance = $this->getGlBankBalance();

        $balanceReconciled = $statementClosingBalance !== null
            ? abs($statementClosingBalance - $glBankBalance) < 0.01
            : null;

        return [
            'total_lines' => $lines->total_lines,
            'matched_lines' => $lines->matched_lines,
            'unmatched_lines' => $lines->unmatched_lines,
            'exception_lines' => $lines->exception_lines,
            'total_credits' => $lines->total_credits,
            'total_debits' => $lines->total_debits,
            'unmatched_amount' => $lines->unmatched_amount,
            'all_lines_matched' => $allMatched,
            'by_category' => $byCategory,
            'gl_bank_balance' => $glBankBalance,
            'statement_closing' => $statementClosingBalance,
            'balance_reconciled' => $balanceReconciled,
            'complete' => $allMatched && ($balanceReconciled ?? true),
            'completion_pct' => $lines->total_lines > 0
                ? round(($lines->matched_lines / $lines->total_lines) * 100, 1)
                : 0,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Auto-match pass — runs all matching strategies in sequence
    // Returns count of newly matched lines
    // ──────────────────────────────────────────────────────────────────────────

    public function autoMatch(int $batchId, string $source = 'business_bank'): array
    {
        $matched = 0;
        $lines = DB::table('bank_statement_lines')
            ->where('import_batch_id', $batchId)
            ->where('match_status', '!=', 'matched')
            ->get();

        foreach ($lines as $line) {
            $result = $this->tryMatchLine($line, $source);
            if ($result) {
                $matched++;
            }
        }

        return ['matched' => $matched, 'remaining' => $lines->count() - $matched];
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function tryMatchLine(object $line, string $source): bool
    {
        $date = $line->transaction_date;
        $dateFrom = \Carbon\Carbon::parse($date)->subDays(2)->toDateString();
        $dateTo = \Carbon\Carbon::parse($date)->addDays(2)->toDateString();

        // ── CREDITS (money in) ─────────────────────────────────────────────
        if ($line->credit_amount > 0) {
            $amount = $line->credit_amount;

            // 1. Nu-Pay collection batch
            if ($source === 'business_bank') {
                $nupay = DB::table('import_batches as ib')
                    ->join('nupay_transactions_stagings as nts', 'nts.import_ref', '=', 'ib.import_ref')
                    ->where('ib.source', 'nupay')
                    ->whereBetween('nts.action_date', [$dateFrom, $dateTo])
                    ->select('ib.id', DB::raw('SUM(nts.instalment_amount) as total'))
                    ->groupBy('ib.id')
                    ->havingRaw('ABS(SUM(nts.instalment_amount) - ?) < ?', [$amount, max(50, $amount * 0.02)])
                    ->first();

                if ($nupay) {
                    $this->matchLine($line->id, 'nu_pay_collection', "Nu-Pay batch #{$nupay->id} — R{$nupay->total}");

                    return true;
                }
            }

            // 2. Facility drawdown
            $draw = DB::table('funding_facility_transactions')
                ->where('transaction_type', 'drawdown')
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->whereRaw('ABS(amount - ?) < ?', [$amount, max(1, $amount * 0.01)])
                ->first();

            if ($draw) {
                $this->matchLine($line->id, 'facility_drawdown', "Facility drawdown — R{$draw->amount}");

                return true;
            }

            // 3. Debt recovery payment
            $recovery = DB::table('debt_recovery_payments')
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->whereRaw('ABS(amount - ?) < 5', [$amount])
                ->first();

            if ($recovery) {
                $this->matchLine($line->id, 'recovery_payment', "Debt recovery payment — R{$recovery->amount}");

                return true;
            }
        }

        // ── DEBITS (money out) ─────────────────────────────────────────────
        if ($line->debit_amount > 0) {
            $amount = $line->debit_amount;

            // 4. Loan disbursement
            $disb = DB::table('loan_disbursements')
                ->where('status', 'released')
                ->whereBetween('disbursement_date', [$dateFrom, $dateTo])
                ->whereRaw('ABS(disbursed_amount - ?) < 5', [$amount])
                ->first();

            if ($disb) {
                $this->matchLine($line->id, 'loan_disbursement', "Loan disbursement #{$disb->id} — R{$disb->disbursed_amount}");

                return true;
            }

            // 5. Facility repayment or interest payment
            $facilityTxn = DB::table('funding_facility_transactions')
                ->whereIn('transaction_type', ['repayment', 'interest_payment', 'fee'])
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->whereRaw('ABS(amount - ?) < 5', [$amount])
                ->first();

            if ($facilityTxn) {
                $this->matchLine($line->id, 'facility_repayment',
                    ucfirst(str_replace('_', ' ', $facilityTxn->transaction_type))." — R{$facilityTxn->amount}");

                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GL posting for expense allocations
    // ──────────────────────────────────────────────────────────────────────────

    protected function postAllocationGl(
        int $lineId,
        int $glAccountId,
        float $amount,
        bool $isDebit,     // true = money OUT (expense), false = money IN (income/credit note)
        string $description,
        int $batchId,
        int $userId
    ): string {

        $bankGl = $this->resolveGl('loan_repayment_dr'); // account 1100 (business bank)
        $expGl = gl_accounts::find($glAccountId);

        if (! $bankGl || ! $expGl) {
            Log::warning("GL accounts not found for bank recon allocation — line #{$lineId}");

            return '';
        }

        $batch = DB::table('import_batches')->find($batchId);
        $customer = \App\Models\Customer::first(); // system placeholder

        $ref = 'ARB-BANKR-'.now()->format('YmdHis').'-'.$lineId;

        $arBatch = arbatch::create([
            'reference' => $ref,
            'customer_id' => $customer?->id ?? 1,
            'source_type' => 'BankStatementLine',
            'source_id' => $lineId,
            'total_amount' => $amount,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        if ($isDebit) {
            // Money OUT: Dr Expense Account / Cr Bank
            $entries = [
                ['arbatch_id' => $arBatch->id, 'gl_account_id' => $expGl->id,  'entry_type' => 'debit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
                ['arbatch_id' => $arBatch->id, 'gl_account_id' => $bankGl->id, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            ];
        } else {
            // Money IN: Dr Bank / Cr GL Account (income/liability reduction)
            $entries = [
                ['arbatch_id' => $arBatch->id, 'gl_account_id' => $bankGl->id, 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
                ['arbatch_id' => $arBatch->id, 'gl_account_id' => $expGl->id,  'entry_type' => 'credit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            ];
        }

        arbatch_entries::insert($entries);
        $this->glPosting->postArBatch($arBatch, $userId);
        $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

        return $ref;
    }

    protected function matchLine(int $lineId, string $category, string $note): void
    {
        DB::table('bank_statement_lines')->where('id', $lineId)->update([
            'match_status' => 'matched',
            'match_category' => $category,
            'match_note' => $note,
        ]);
    }

    protected function getGlBankBalance(): float
    {
        $bankGl = $this->resolveGl('loan_repayment_dr');

        return $bankGl ? (float) $bankGl->current_balance : 0;
    }

    protected function resolveGl(string $key): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            return null;
        }

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $mapping->account_code))->first();
    }
}
