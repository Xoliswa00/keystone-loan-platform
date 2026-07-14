<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\LoanDisbursement;
use App\Models\LoanFee;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisbursementService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    /**
     * Approve a disbursement, post all GL entries atomically:
     *   – Principal (Loans Receivable Dr / Bank Cr)
     *   – Deferred fees (Cash Dr / Deferred Income Cr)  ← multi-month only
     *   – For 1-month loans, fees recognised immediately (Fee Income Cr)
     *
     * Then initialise loan balances correctly.
     */
    public function approveAndPost(int $disbursementId, int $approverId): LoanDisbursement
    {
        return DB::transaction(function () use ($disbursementId, $approverId) {

            /** @var LoanDisbursement $disb */
            $disb = LoanDisbursement::lockForUpdate()->findOrFail($disbursementId);

            if ($disb->status !== 'waiting_for_approval') {
                throw new Exception("Cannot approve disbursement in state: {$disb->status}");
            }

            $loan = $disb->loan;
            $customer = $loan->user?->customer;

            if (! $customer) {
                throw new Exception("No customer record found for loan #{$loan->id}.");
            }

            // ── 1. Stamp disbursement approved ────────────────────────────────
            $disb->update([
                'status' => 'approved',
                'payment_realiser_id' => $approverId,
                'disbursement_date' => now(),
            ]);

            // ── 2. Load fee snapshot ──────────────────────────────────────────
            $fee = LoanFee::where('loan_application_id', $loan->loan_application_id)->first();

            $principal = (float) $disb->disbursed_amount;
            $totalInterest = $fee ? (float) $fee->interest_amount : 0;
            $initiationFee = $fee ? (float) $fee->initiation_fee : 0;
            $serviceFeeTotal = $fee ? (float) $fee->service_fee : 0;
            $totalFees = $initiationFee + $serviceFeeTotal;
            $totalDue = $principal + $totalInterest + $totalFees;

            $months = $loan->loan_term_months ?? 1;
            $isMulti = $months > 1;

            // ── 3. Resolve GL accounts ────────────────────────────────────────
            $branchId = $loan->branch_id ?? 1;
            $locationCode = $loan->location_code ?? '000';

            $loanReceivableGl = $this->getGlAccountFor('loan_disbursement_dr', $branchId, $locationCode);
            $bankGl = $this->getGlAccountFor('loan_disbursement_cr', $branchId, $locationCode);
            $deferredInterestGl = $this->getGlAccountFor('deferred_interest_cr', $branchId, $locationCode);
            $deferredFeeGl = $this->getGlAccountFor('deferred_fee_cr', $branchId, $locationCode);
            $feeIncomeGl = $this->getGlAccountFor('fee_income_cr', $branchId, $locationCode);
            $interestIncomeGl = $this->getGlAccountFor('interest_income_cr', $branchId, $locationCode);

            if (! $loanReceivableGl || ! $bankGl) {
                throw new Exception('Missing GL account mapping for loan receivable or bank.');
            }

            // ── 4. Build AR batch ─────────────────────────────────────────────
            $ref = 'ARB-DISB-'.now()->format('YmdHis').'-'.$disb->id;
            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => LoanDisbursement::class,
                'source_id' => $disb->id,
                'total_amount' => $principal,
                'status' => 'approved',
                'created_by' => $approverId,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            $entries = [];

            // Entry 1: Dr Loans Receivable (principal)
            $entries[] = [
                'arbatch_id' => $arBatch->id,
                'gl_account_id' => $loanReceivableGl->id,
                'entry_type' => 'debit',
                'amount' => $principal,
                'description' => "Loan #{$loan->id} — principal disbursed",
                'loan_id' => $loan->id,
                'created_at' => now(), 'updated_at' => now(),
            ];

            // Entry 2: Cr Bank (cash out)
            $entries[] = [
                'arbatch_id' => $arBatch->id,
                'gl_account_id' => $bankGl->id,
                'entry_type' => 'credit',
                'amount' => $principal,
                'description' => "Bank — cash disbursed for loan #{$loan->id}",
                'loan_id' => $loan->id,
                'created_at' => now(), 'updated_at' => now(),
            ];

            // Fee & interest recognition
            if ($isMulti) {
                // Multi-month: defer all income — recognise monthly as installments paid
                if ($totalInterest > 0 && $deferredInterestGl) {
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $deferredInterestGl->id,
                        'entry_type' => 'credit',
                        'amount' => $totalInterest,
                        'description' => "Deferred interest income — loan #{$loan->id} ({$months} months)",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    // Matching debit on loans receivable (increase the gross receivable)
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $loanReceivableGl->id,
                        'entry_type' => 'debit',
                        'amount' => $totalInterest,
                        'description' => "Gross interest receivable — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }

                if ($totalFees > 0 && $deferredFeeGl) {
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $deferredFeeGl->id,
                        'entry_type' => 'credit',
                        'amount' => $totalFees,
                        'description' => "Deferred fee income — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $loanReceivableGl->id,
                        'entry_type' => 'debit',
                        'amount' => $totalFees,
                        'description' => "Gross fee receivable — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            } else {
                // Single-month: recognise all income immediately on disbursement
                if ($totalInterest > 0 && $interestIncomeGl) {
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $interestIncomeGl->id,
                        'entry_type' => 'credit',
                        'amount' => $totalInterest,
                        'description' => "Interest income — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $loanReceivableGl->id,
                        'entry_type' => 'debit',
                        'amount' => $totalInterest,
                        'description' => "Interest receivable — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }

                if ($totalFees > 0 && $feeIncomeGl) {
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $feeIncomeGl->id,
                        'entry_type' => 'credit',
                        'amount' => $totalFees,
                        'description' => "Fee income — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $entries[] = [
                        'arbatch_id' => $arBatch->id,
                        'gl_account_id' => $loanReceivableGl->id,
                        'entry_type' => 'debit',
                        'amount' => $totalFees,
                        'description' => "Fee receivable — loan #{$loan->id}",
                        'loan_id' => $loan->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }

            arbatch_entries::insert($entries);

            // ── 5. Post to GL ──────────────────────────────────────────────────
            $this->glPosting->postArBatch($arBatch, $approverId);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // ── 6. Initialise loan balances ────────────────────────────────────
            // remaining_balance = total amount owed by client (principal + interest + fees)
            // This is what reduces with each payment received.
            $loan->update([
                'status' => 'disbursed',
                'disbursed_date' => now()->toDateString(),
                'principal_amount' => $principal,
                'total_interest' => round($totalInterest, 2),
                'total_fees_excl_vat' => round($totalFees / (1 + ($loan->product->vat_rate ?? 0.15)), 2),
                'total_vat' => round($totalFees - ($totalFees / (1 + ($loan->product->vat_rate ?? 0.15))), 2),
                'total_amount_due' => round($totalDue, 2),
                'remaining_balance' => round($totalDue, 2), // starts at total due
                'deferred_interest' => $isMulti ? round($totalInterest, 2) : 0,
                'deferred_fees' => $isMulti ? round($totalFees, 2) : 0,
            ]);

            // Customer outstanding balance = total amount they owe us
            $customer->update([
                'current_balance' => DB::raw("current_balance + {$totalDue}"),
            ]);

            // ── 7. Mark disbursement released ─────────────────────────────────
            // disbursement_method is always bank_transfer — the only
            // fulfillment channel this system actually implements (no cash/
            // cheque handling anywhere in the disbursement flow).
            $disb->update([
                'status' => 'released',
                'released_at' => now(),
                'disbursement_method' => 'bank_transfer',
            ]);

            Log::info("Disbursement #{$disb->id} approved and posted.", [
                'loan_id' => $loan->id,
                'principal' => $principal,
                'total_due' => $totalDue,
                'months' => $months,
                'approver_id' => $approverId,
            ]);

            return $disb->fresh(['loan', 'loan.user', 'loan.user.customer']);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Recognise earned income when an installment is paid (multi-month loans).
     * Call this inside LoanRepaymentController when a payment is confirmed.
     */
    public function recogniseInstallmentIncome(int $scheduleId, int $userId): void
    {
        DB::transaction(function () use ($scheduleId, $userId) {
            $schedule = \App\Models\RepaymentSchedule::lockForUpdate()->findOrFail($scheduleId);
            $loan = $schedule->loanApplication?->loan;

            if (! $loan || $loan->loan_term_months <= 1) {
                return; // single-month: income already posted at disbursement
            }

            if ($schedule->gl_posted) {
                return; // already recognised
            }

            $branchId = $loan->branch_id ?? 1;
            $locationCode = $loan->location_code ?? '000';

            $deferredInterestGl = $this->getGlAccountFor('deferred_interest_cr', $branchId, $locationCode);
            $interestIncomeGl = $this->getGlAccountFor('interest_income_cr', $branchId, $locationCode);
            $deferredFeeGl = $this->getGlAccountFor('deferred_fee_cr', $branchId, $locationCode);
            $feeIncomeGl = $this->getGlAccountFor('fee_income_cr', $branchId, $locationCode);

            $customer = $loan->user->customer;

            $ref = 'ARB-REPAY-'.now()->format('YmdHis').'-'.$scheduleId;
            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => \App\Models\RepaymentSchedule::class,
                'source_id' => $scheduleId,
                'total_amount' => $schedule->emi_amount,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $entries = [];

            $interest = (float) $schedule->interest_amount;
            $fee = (float) $schedule->fee_amount;

            if ($interest > 0 && $deferredInterestGl && $interestIncomeGl) {
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $deferredInterestGl->id,
                    'entry_type' => 'debit',  'amount' => $interest,
                    'description' => "Deferred interest released — schedule #{$scheduleId}",
                    'created_at' => now(), 'updated_at' => now()];
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $interestIncomeGl->id,
                    'entry_type' => 'credit', 'amount' => $interest,
                    'description' => "Interest income recognised — schedule #{$scheduleId}",
                    'created_at' => now(), 'updated_at' => now()];
            }

            if ($fee > 0 && $deferredFeeGl && $feeIncomeGl) {
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $deferredFeeGl->id,
                    'entry_type' => 'debit',  'amount' => $fee,
                    'description' => "Deferred fee released — schedule #{$scheduleId}",
                    'created_at' => now(), 'updated_at' => now()];
                $entries[] = ['arbatch_id' => $arBatch->id, 'gl_account_id' => $feeIncomeGl->id,
                    'entry_type' => 'credit', 'amount' => $fee,
                    'description' => "Fee income recognised — schedule #{$scheduleId}",
                    'created_at' => now(), 'updated_at' => now()];
            }

            if (! empty($entries)) {
                arbatch_entries::insert($entries);
                $this->glPosting->postArBatch($arBatch, $userId);
            }

            // Reduce deferred balances on the loan
            $loan->decrement('deferred_interest', $interest);
            $loan->decrement('deferred_fees', $fee);

            $schedule->update(['gl_posted' => true]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function getGlAccountFor(string $key, $branchId = null, $locationCode = null): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();

        if (! $mapping) {
            Log::warning("GL mapping not found for key: {$key}");

            return null;
        }

        $code = $mapping->account_code;

        $query = gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        return $query->first()
            ?? gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
                ->where('branch_id', 1)->first();
    }
}
