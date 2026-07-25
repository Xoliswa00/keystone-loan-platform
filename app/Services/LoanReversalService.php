<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\AuditLog;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanFee;
use App\Models\LoanRepayment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Undo a loan approval/disbursement that turns out to have been a mistake.
 * Exposed as one staff-facing action ("Reverse") because the intent is the
 * same regardless of how far it got — but the mechanics branch hard on how
 * far it actually got:
 *
 *  - Not yet disbursed: cheap undo, no GL involved (DisbursementService::
 *    approveAndPost() — the only place that posts GL for a disbursement —
 *    never ran). Delete the Loan + LoanDisbursement rows, back to pending.
 *  - Disbursed (GL posted): full reversal, blocked if any repayment has
 *    been made against it (reverse those first via
 *    ManualPaymentService::reversePayment(), same ordering the payment
 *    reversal comment there assumes). Posts a reversal batch that swaps
 *    every entry DisbursementService::approveAndPost() created.
 *  - Settled, or has payment history: blocked outright.
 */
class LoanReversalService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    public function reverseApproval(LoanApplication $application, User $staff, string $reason): void
    {
        DB::transaction(function () use ($application, $staff, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== 'approved') {
                throw new Exception("Application #{$application->id} is not currently approved — nothing to reverse.");
            }

            $loan = Loan::where('loan_application_id', $application->id)->lockForUpdate()->first();
            $disbursement = $loan ? LoanDisbursement::where('loan_id', $loan->id)->lockForUpdate()->first() : null;

            $oldValues = ['application_status' => $application->status, 'loan_status' => $loan?->status, 'disbursement_status' => $disbursement?->status];

            if (! $loan || ! $disbursement || $disbursement->status === 'waiting_for_approval') {
                $this->reverseBeforeDisbursement($application, $loan, $disbursement);
            } else {
                $this->reverseAfterDisbursement($application, $loan, $disbursement, $staff);
            }

            AuditLog::record('reversed', $application, $oldValues, ['application_status' => 'pending'], $reason);
        });

        $this->notify($application->fresh(), $reason);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Not yet disbursed — nothing GL-posted, a clean status/row undo
    // ──────────────────────────────────────────────────────────────────────

    protected function reverseBeforeDisbursement(LoanApplication $application, ?Loan $loan, ?LoanDisbursement $disbursement): void
    {
        $disbursement?->delete();
        $loan?->delete();

        $application->update([
            'status' => 'pending',
            'approval_date' => null,
            'reviewer_id' => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Disbursed — GL was posted by DisbursementService::approveAndPost();
    // reverse every entry it created.
    // ──────────────────────────────────────────────────────────────────────

    protected function reverseAfterDisbursement(LoanApplication $application, Loan $loan, LoanDisbursement $disbursement, User $staff): void
    {
        if (LoanRepayment::where('loan_id', $loan->id)->where('status', 'paid')->exists()) {
            throw new Exception('This loan has repayment history — reverse those payments first before reversing the disbursement.');
        }

        if (! in_array($loan->status, ['disbursed', 'payment_failed'], true)) {
            throw new Exception("Loan #{$loan->id} is {$loan->status} — cannot be reversed this way.");
        }

        $customer = $loan->user->customer;
        $fee = LoanFee::where('loan_application_id', $loan->loan_application_id)->first();

        $principal = (float) $disbursement->disbursed_amount;
        $totalInterest = $fee ? (float) $fee->interest_amount : 0;
        $totalFees = $fee ? ((float) $fee->initiation_fee + (float) $fee->service_fee) : 0;
        $totalDue = $principal + $totalInterest + $totalFees;
        $isMulti = ($loan->loan_term_months ?? 1) > 1;

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

        $ref = 'ARB-REVERSAL-'.now()->format('YmdHis').'-'.$disbursement->id;
        $arBatch = arbatch::create([
            'reference' => $ref,
            'customer_id' => $customer->id,
            'source_type' => LoanDisbursement::class,
            'source_id' => $disbursement->id,
            'total_amount' => $principal,
            'status' => 'approved',
            'created_by' => $staff->id,
            'approved_by' => $staff->id,
            'approved_at' => now(),
        ]);

        $entries = [];

        // Reversal = swap every Dr/Cr approveAndPost() posted.
        $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $principal,
            "Reversal — loan #{$loan->id} principal disbursement reversed", $loan->id);
        $entries[] = $this->entry($arBatch->id, $bankGl->id, 'debit', $principal,
            "Reversal — bank, loan #{$loan->id} disbursement reversed", $loan->id);

        if ($isMulti) {
            if ($totalInterest > 0 && $deferredInterestGl) {
                $entries[] = $this->entry($arBatch->id, $deferredInterestGl->id, 'debit', $totalInterest,
                    "Reversal — deferred interest income, loan #{$loan->id}", $loan->id);
                $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $totalInterest,
                    "Reversal — gross interest receivable, loan #{$loan->id}", $loan->id);
            }
            if ($totalFees > 0 && $deferredFeeGl) {
                $entries[] = $this->entry($arBatch->id, $deferredFeeGl->id, 'debit', $totalFees,
                    "Reversal — deferred fee income, loan #{$loan->id}", $loan->id);
                $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $totalFees,
                    "Reversal — gross fee receivable, loan #{$loan->id}", $loan->id);
            }
        } else {
            if ($totalInterest > 0 && $interestIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $interestIncomeGl->id, 'debit', $totalInterest,
                    "Reversal — interest income, loan #{$loan->id}", $loan->id);
                $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $totalInterest,
                    "Reversal — interest receivable, loan #{$loan->id}", $loan->id);
            }
            if ($totalFees > 0 && $feeIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $feeIncomeGl->id, 'debit', $totalFees,
                    "Reversal — fee income, loan #{$loan->id}", $loan->id);
                $entries[] = $this->entry($arBatch->id, $loanReceivableGl->id, 'credit', $totalFees,
                    "Reversal — fee receivable, loan #{$loan->id}", $loan->id);
            }
        }

        arbatch_entries::insert($entries);

        $this->glPosting->postArBatch($arBatch, $staff->id);
        $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

        $loan->update([
            'status' => 'reversed',
            'remaining_balance' => 0,
            'deferred_interest' => 0,
            'deferred_fees' => 0,
        ]);

        $disbursement->update(['status' => 'reversed']);

        $customer->update(['current_balance' => DB::raw("current_balance - {$totalDue}")]);

        $application->update([
            'status' => 'pending',
            'approval_date' => null,
            'reviewer_id' => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────

    protected function notify(LoanApplication $application, string $reason): void
    {
        try {
            \Illuminate\Support\Facades\Mail::to($application->user->email)->queue(
                new \App\Mail\LoanNotificationMail(
                    'Loan Approval Reversed',
                    [
                        'Your loan approval (application #'.str_pad($application->id, 6, '0', STR_PAD_LEFT).') has been reversed by our team.',
                        'Reason: '.$reason,
                        'Your application is back under review — we\'ll be in touch.',
                    ],
                    $application
                )
            );
        } catch (Exception $e) {
            Log::warning('Loan reversal notification failed: '.$e->getMessage());
        }
    }

    protected function entry(int $batchId, int $accountId, string $type, float $amount, string $desc, ?int $loanId = null): array
    {
        return [
            'arbatch_id' => $batchId,
            'gl_account_id' => $accountId,
            'entry_type' => $type,
            'amount' => round($amount, 2),
            'description' => $desc,
            'loan_id' => $loanId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

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

        return $query->first()
            ?? gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
                ->where('branch_id', 1)->first();
    }
}
