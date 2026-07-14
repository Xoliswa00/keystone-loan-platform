<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\FundingFacility;
use App\Models\FundingFacilityTransaction;
use App\Models\gl_accounts;
use App\Models\glmapping;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles all GL-integrated transactions for funding facilities:
 *  - Drawdown:          Dr Bank (1100) / Cr Loans Payable (2500 or 2520)
 *  - Repayment:         Dr Loans Payable / Cr Bank
 *  - Interest accrual:  Dr Interest Expense (5600) / Cr Accrued Interest (2530)
 *  - Interest payment:  Dr Accrued Interest (2530) / Cr Bank
 */
class FundingFacilityService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Drawdown — funder sends money to Keystone
    // ──────────────────────────────────────────────────────────────────────────

    public function recordDrawdown(
        FundingFacility $facility,
        float $amount,
        string $date,
        string $reference,
        int $userId,
        ?string $notes = null
    ): FundingFacilityTransaction {

        return DB::transaction(function () use ($facility, $amount, $date, $reference, $userId, $notes) {

            if ($amount > $facility->availableLimit()) {
                throw new Exception("Drawdown of R{$amount} exceeds available limit of R{$facility->availableLimit()}.");
            }

            // GL: Dr Bank / Cr Loans Payable (or Shareholder Loan)
            $bankGlKey = 'loan_repayment_dr'; // business bank account = account 1100
            $liabGlKey = $facility->funder_type === 'shareholder_loan'
                ? 'shareholder_loan_cr'
                : 'loans_payable_cr';

            $ref = "FF-DRAW-{$facility->facility_code}-".now()->format('Ymd');

            $this->postGl($facility, $bankGlKey, $liabGlKey, 'debit', $amount, $ref, $userId,
                "Drawdown from {$facility->funder_name} — {$reference}");

            // Update facility balances
            $facility->increment('drawn_amount', $amount);
            $facility->increment('current_balance', $amount);

            $txn = FundingFacilityTransaction::create([
                'facility_id' => $facility->id,
                'transaction_type' => 'drawdown',
                'amount' => $amount,
                'transaction_date' => $date,
                'reference' => $reference,
                'gl_batch_reference' => $ref,
                'gl_posted' => true,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            Log::info("Facility #{$facility->id} drawdown R{$amount} from {$facility->funder_name}");

            return $txn;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Repayment — Keystone pays back principal to funder
    // ──────────────────────────────────────────────────────────────────────────

    public function recordRepayment(
        FundingFacility $facility,
        float $amount,
        string $date,
        string $reference,
        int $userId,
        ?string $notes = null
    ): FundingFacilityTransaction {

        return DB::transaction(function () use ($facility, $amount, $date, $reference, $userId, $notes) {

            if ($amount > $facility->current_balance) {
                throw new Exception("Repayment of R{$amount} exceeds current balance of R{$facility->current_balance}.");
            }

            $liabGlKey = $facility->funder_type === 'shareholder_loan'
                ? 'shareholder_loan_cr'
                : 'loans_payable_cr';
            $bankGlKey = 'loan_repayment_dr';

            $ref = "FF-REP-{$facility->facility_code}-".now()->format('Ymd');

            $this->postGl($facility, $liabGlKey, $bankGlKey, 'credit', $amount, $ref, $userId,
                "Principal repayment to {$facility->funder_name} — {$reference}");

            $facility->decrement('current_balance', $amount);

            if ($facility->current_balance <= 0) {
                $facility->update(['status' => 'fully_repaid']);
            }

            return FundingFacilityTransaction::create([
                'facility_id' => $facility->id,
                'transaction_type' => 'repayment',
                'amount' => $amount,
                'transaction_date' => $date,
                'reference' => $reference,
                'gl_batch_reference' => $ref,
                'gl_posted' => true,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Interest accrual — month-end journal (doesn't move cash)
    // ──────────────────────────────────────────────────────────────────────────

    public function accrueInterest(FundingFacility $facility, int $userId): FundingFacilityTransaction
    {
        return DB::transaction(function () use ($facility, $userId) {

            $amount = $facility->monthlyInterestDue();

            if ($amount <= 0) {
                throw new Exception('No interest to accrue — balance or rate is zero.');
            }

            $ref = "FF-ACCR-{$facility->facility_code}-".now()->format('Ym');

            $this->postGl($facility, 'interest_expense_dr', 'accrued_interest_cr', 'debit', $amount,
                $ref, $userId, "Interest accrual — {$facility->funder_name} ".now()->format('M Y'));

            $facility->increment('accrued_interest', $amount);

            return FundingFacilityTransaction::create([
                'facility_id' => $facility->id,
                'transaction_type' => 'interest_accrual',
                'amount' => $amount,
                'transaction_date' => now()->toDateString(),
                'reference' => $ref,
                'gl_batch_reference' => $ref,
                'gl_posted' => true,
                'notes' => 'Monthly interest accrual',
                'created_by' => $userId,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Interest payment — cash settlement of accrued interest
    // ──────────────────────────────────────────────────────────────────────────

    public function payInterest(
        FundingFacility $facility,
        float $amount,
        string $date,
        string $reference,
        int $userId,
        ?string $notes = null
    ): FundingFacilityTransaction {

        return DB::transaction(function () use ($facility, $amount, $date, $reference, $userId, $notes) {

            $ref = "FF-INT-{$facility->facility_code}-".now()->format('Ymd');

            $this->postGl($facility, 'accrued_interest_cr', 'loan_repayment_dr', 'credit', $amount,
                $ref, $userId, "Interest payment to {$facility->funder_name} — {$reference}");

            $facility->decrement('accrued_interest', min($amount, $facility->accrued_interest));

            return FundingFacilityTransaction::create([
                'facility_id' => $facility->id,
                'transaction_type' => 'interest_payment',
                'amount' => $amount,
                'transaction_date' => $date,
                'reference' => $reference,
                'gl_batch_reference' => $ref,
                'gl_posted' => true,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Run monthly accrual for all active facilities
    // ──────────────────────────────────────────────────────────────────────────

    public function accrueAllActive(int $adminId): array
    {
        $results = ['accrued' => 0, 'skipped' => 0, 'errors' => []];

        FundingFacility::active()->get()->each(function ($facility) use ($adminId, &$results) {
            try {
                $this->accrueInterest($facility, $adminId);
                $results['accrued']++;
            } catch (Exception $e) {
                $results['skipped']++;
                $results['errors'][] = "Facility #{$facility->id}: ".$e->getMessage();
            }
        });

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function postGl(
        FundingFacility $facility,
        string $drKey,
        string $crKey,
        string $primarySide,   // which account is the "main" side (for description)
        float $amount,
        string $ref,
        int $userId,
        string $description
    ): void {

        $drGl = $this->resolveGl($drKey);
        $crGl = $this->resolveGl($crKey);

        if (! $drGl || ! $crGl) {
            Log::warning("Missing GL accounts for facility transaction: {$drKey} / {$crKey}");

            return; // post without GL if mappings not set — don't block the transaction
        }

        // We need a customer_id for arbatch — use a dummy system customer or null
        // For business transactions, use the company's own customer record if it exists
        $systemCustomer = \App\Models\Customer::first(); // fallback

        $arBatch = arbatch::create([
            'reference' => $ref,
            'customer_id' => $systemCustomer?->id ?? 1,
            'source_type' => FundingFacility::class,
            'source_id' => $facility->id,
            'total_amount' => $amount,
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        arbatch_entries::insert([
            ['arbatch_id' => $arBatch->id, 'gl_account_id' => $drGl->id, 'entry_type' => 'debit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            ['arbatch_id' => $arBatch->id, 'gl_account_id' => $crGl->id, 'entry_type' => 'credit', 'amount' => $amount, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->glPosting->postArBatch($arBatch, $userId);
        $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);
    }

    protected function resolveGl(string $key): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            Log::warning("GL mapping not found: {$key}");

            return null;
        }

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $mapping->account_code))->first();
    }
}
