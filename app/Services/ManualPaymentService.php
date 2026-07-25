<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payments that don't come through NuPay's debit-order collection — a
 * client paying by EFT/cash directly, outside that system. Two entry
 * points, one posting path:
 *
 *  - Client uploads proof of payment (submitForReview) → sits as
 *    'pending_review'. Nothing is "paid" yet — no balance, schedule, or GL
 *    change — until staff verify it (recordAndPost) or reject it (reject).
 *  - Staff record a cash/EFT payment directly on a client's behalf
 *    (recordAndPost, called straight from the controller with no prior
 *    'pending_review' row) — staff's own action is the attestation, so this
 *    posts immediately, no separate approval step.
 *
 * Either way, recordAndPost() is the only place money actually moves — same
 * GL accounts NuPayService::handleSuccess() uses (loan_repayment_dr /
 * loan_disbursement_dr / deferred income release), so a manual payment
 * shows up in the trial balance exactly like a NuPay one. Before this,
 * LoanRepaymentController::store() only mutated loans.remaining_balance /
 * customers.current_balance directly — despite a comment claiming it
 * "posts to GL," it never created a real GL batch.
 */
class ManualPaymentService
{
    protected GLPostingService $glPosting;

    public function __construct(GLPostingService $glPosting)
    {
        $this->glPosting = $glPosting;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Client: submit proof of payment — no money movement yet
    // ──────────────────────────────────────────────────────────────────────

    public function submitForReview(RepaymentSchedule $schedule, User $client, array $data): LoanRepayment
    {
        $loanApplication = $schedule->loanApplication;
        $loan = $loanApplication?->loan;

        if (! $loan) {
            throw new Exception('Cannot find the associated loan record.');
        }

        return LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $client->id,
            'repayment_schedule_id' => $schedule->id,
            'payment_amount' => $data['payment_amount'],
            'principal_amount' => $schedule->principal_amount,
            'interest_amount' => $schedule->interest_amount,
            'fee_amount' => $schedule->fee_amount,
            'payment_date' => $data['payment_date'],
            'due_date' => $schedule->due_date,
            'status' => 'pending_review',
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'payment_reference' => $data['payment_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'proof_of_payment_path' => $data['proof_of_payment_path'],
            'submitted_by' => $client->id,
            'transaction_type' => 'manual',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Post a manual payment for real — GL batch + balance + schedule.
    // Either creates a fresh repayment (staff direct-entry) or finalises an
    // existing 'pending_review' one (approving a client's submitted proof).
    // ──────────────────────────────────────────────────────────────────────

    public function recordAndPost(
        RepaymentSchedule $schedule,
        array $data,
        User $staff,
        ?LoanRepayment $existing = null
    ): LoanRepayment {
        return DB::transaction(function () use ($schedule, $data, $staff, $existing) {
            $schedule = RepaymentSchedule::lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->status === 'paid') {
                throw new Exception('This instalment has already been paid.');
            }

            $loanApplication = $schedule->loanApplication;
            $loan = $loanApplication?->loan;

            if (! $loan) {
                throw new Exception('Cannot find the associated loan record.');
            }

            $customer = $loan->user->customer;

            $grossAmount = round((float) $data['payment_amount'], 2);
            $principalAmount = round((float) $schedule->principal_amount, 2);
            $interestAmount = round((float) $schedule->interest_amount, 2);
            $feeAmount = round((float) $schedule->fee_amount, 2);

            // ── AR batch + GL ────────────────────────────────────────────────
            $ref = 'ARB-MANUAL-'.now()->format('YmdHis').'-'.$schedule->id;
            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => RepaymentSchedule::class,
                'source_id' => $schedule->id,
                'total_amount' => $grossAmount,
                'status' => 'approved',
                'created_by' => $staff->id,
                'approved_by' => $staff->id,
                'approved_at' => now(),
            ]);

            $bankGl = $this->resolveGl('loan_repayment_dr');
            $loanReceivGl = $this->resolveGl('loan_disbursement_dr');
            $intIncomeGl = $this->resolveGl('interest_income_cr');
            $feeIncomeGl = $this->resolveGl('fee_income_cr');
            $deferredIntGl = $this->resolveGl('deferred_interest_cr');
            $deferredFeeGl = $this->resolveGl('deferred_fee_cr');

            if (! $bankGl || ! $loanReceivGl) {
                throw new Exception('Missing required GL accounts for payment posting.');
            }

            $isMulti = ($loan->loan_term_months ?? 1) > 1;
            $entries = [];

            $entries[] = $this->entry($arBatch->id, $bankGl->id, 'debit', $grossAmount,
                "Manual payment received — schedule #{$schedule->id}");

            $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $principalAmount,
                "Principal repayment — loan #{$loan->id} instalment #{$schedule->installment_number}");

            if ($isMulti) {
                if ($interestAmount > 0 && $deferredIntGl && $intIncomeGl) {
                    $entries[] = $this->entry($arBatch->id, $deferredIntGl->id, 'debit', $interestAmount,
                        "Deferred interest released — instalment #{$schedule->installment_number}");
                    $entries[] = $this->entry($arBatch->id, $intIncomeGl->id, 'credit', $interestAmount,
                        "Interest income recognised — instalment #{$schedule->installment_number}");
                }
                if ($feeAmount > 0 && $deferredFeeGl && $feeIncomeGl) {
                    $entries[] = $this->entry($arBatch->id, $deferredFeeGl->id, 'debit', $feeAmount,
                        "Deferred fee released — instalment #{$schedule->installment_number}");
                    $entries[] = $this->entry($arBatch->id, $feeIncomeGl->id, 'credit', $feeAmount,
                        "Fee income recognised — instalment #{$schedule->installment_number}");
                }
            } else {
                // Single-month: income was already recognised at disbursement —
                // just balance the credits, same as NuPayService::handleSuccess().
                if ($interestAmount > 0) {
                    $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $interestAmount,
                        "Interest receivable cleared — loan #{$loan->id}");
                }
                if ($feeAmount > 0) {
                    $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $feeAmount,
                        "Fee receivable cleared — loan #{$loan->id}");
                }
            }

            arbatch_entries::insert($entries);

            $this->glPosting->postArBatch($arBatch, $staff->id);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // ── Balances ─────────────────────────────────────────────────────
            $loan->decrement('remaining_balance', $grossAmount);
            $customer->decrement('current_balance', $grossAmount);

            if ($isMulti) {
                $loan->decrement('deferred_interest', $interestAmount);
                $loan->decrement('deferred_fees', $feeAmount);
            }

            $schedule->update(['status' => 'paid', 'paid_at' => now(), 'gl_posted' => true]);

            $unpaidCount = RepaymentSchedule::where('loan_id', $loanApplication->id)
                ->where('status', 'pending')->count();
            $loan->update(['status' => $unpaidCount === 0 ? 'settled' : 'disbursed']);

            // ── Repayment record ─────────────────────────────────────────────
            $attributes = [
                'loan_id' => $loan->id,
                'repayment_schedule_id' => $schedule->id,
                'payment_amount' => $grossAmount,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'fee_amount' => $feeAmount,
                'payment_date' => $data['payment_date'],
                'due_date' => $schedule->due_date,
                'status' => 'paid',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_received_by' => $staff->id,
                'gl_batch_reference' => $arBatch->reference,
                'transaction_type' => 'manual',
                'verified_by' => $staff->id,
                'verified_at' => now(),
            ];

            if ($existing) {
                $existing->update($attributes);
                $repayment = $existing->fresh();
            } else {
                $attributes['user_id'] = $loan->user_id;
                if (isset($data['proof_of_payment_path'])) {
                    $attributes['proof_of_payment_path'] = $data['proof_of_payment_path'];
                }
                $repayment = LoanRepayment::create($attributes);
            }

            try {
                $loan->user?->notify(new \App\Notifications\PaymentReceivedNotification($repayment));
            } catch (Exception $e) {
                Log::warning('Manual payment notification failed: '.$e->getMessage());
            }

            return $repayment;
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Reject a client-submitted proof of payment — no balance/GL change
    // ──────────────────────────────────────────────────────────────────────

    public function reject(LoanRepayment $repayment, User $staff, string $reason): void
    {
        DB::transaction(function () use ($repayment, $staff, $reason) {
            $repayment = LoanRepayment::lockForUpdate()->findOrFail($repayment->id);

            if ($repayment->status !== 'pending_review') {
                throw new Exception('Only a pending payment claim can be rejected.');
            }

            $repayment->update([
                'status' => 'rejected',
                'verified_by' => $staff->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });

        $repayment->refresh();

        try {
            if ($repayment->user?->email) {
                \Illuminate\Support\Facades\Mail::to($repayment->user->email)->queue(
                    new \App\Mail\LoanNotificationMail(
                        'Proof of Payment Declined',
                        [
                            'The proof of payment you submitted for R'.number_format((float) $repayment->payment_amount, 2).
                                ' could not be verified.',
                            'Reason: '.$reason,
                            'If you believe this is a mistake, please contact us or submit a corrected proof of payment.',
                        ],
                        $repayment->loan
                    )
                );
            }
        } catch (Exception $e) {
            Log::warning('Manual payment rejection notification failed: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Reverse an already-posted payment (manual or NuPay origin — the
    // technique doesn't care which). Staff-initiated equivalent of
    // NuPayService::handleReversed(), which only fires automatically when
    // NuPay's own feed sends a reversal transaction. Never mutates the
    // original row — creates an offsetting negative one, same
    // non-destructive pattern handleReversed() already uses, so both the
    // original payment and its correction stay visible in the audit trail.
    // ──────────────────────────────────────────────────────────────────────

    public function reversePayment(LoanRepayment $original, User $staff, string $reason): LoanRepayment
    {
        return DB::transaction(function () use ($original, $staff, $reason) {
            $original = LoanRepayment::lockForUpdate()->findOrFail($original->id);

            if ($original->status !== 'paid') {
                throw new Exception('Only a posted (paid) payment can be reversed.');
            }

            if (LoanRepayment::where('reverses_repayment_id', $original->id)->exists()) {
                throw new Exception('This payment has already been reversed.');
            }

            $schedule = RepaymentSchedule::lockForUpdate()->findOrFail($original->repayment_schedule_id);
            $loan = $schedule->loanApplication?->loan;

            if (! $loan) {
                throw new Exception('Cannot find the associated loan record.');
            }

            $customer = $loan->user->customer;

            $grossAmount = round((float) $original->payment_amount, 2);
            $principalAmount = round((float) $original->principal_amount, 2);
            $interestAmount = round((float) $original->interest_amount, 2);
            $feeAmount = round((float) $original->fee_amount, 2);

            $ref = 'ARB-REVERSAL-'.now()->format('YmdHis').'-'.$original->id;
            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => LoanRepayment::class,
                'source_id' => $original->id,
                'total_amount' => $grossAmount,
                'status' => 'approved',
                'created_by' => $staff->id,
                'approved_by' => $staff->id,
                'approved_at' => now(),
            ]);

            $bankGl = $this->resolveGl('loan_repayment_dr');
            $loanReceivGl = $this->resolveGl('loan_disbursement_dr');
            $intIncomeGl = $this->resolveGl('interest_income_cr');
            $feeIncomeGl = $this->resolveGl('fee_income_cr');
            $deferredIntGl = $this->resolveGl('deferred_interest_cr');
            $deferredFeeGl = $this->resolveGl('deferred_fee_cr');

            if (! $bankGl || ! $loanReceivGl) {
                throw new Exception('Missing required GL accounts for payment reversal.');
            }

            $isMulti = ($loan->loan_term_months ?? 1) > 1;
            $entries = [];

            // Reversal = swap Dr/Cr from the original posting entries
            $entries[] = $this->entry($arBatch->id, $bankGl->id, 'credit', $grossAmount,
                "Reversal — bank #{$original->id}");
            $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $principalAmount,
                "Reversal — principal re-opened #{$original->id}");

            if ($isMulti) {
                if ($interestAmount > 0 && $deferredIntGl && $intIncomeGl) {
                    $entries[] = $this->entry($arBatch->id, $intIncomeGl->id, 'debit', $interestAmount, 'Reversal — interest income reversed');
                    $entries[] = $this->entry($arBatch->id, $deferredIntGl->id, 'credit', $interestAmount, 'Reversal — deferred interest restored');
                }
                if ($feeAmount > 0 && $deferredFeeGl && $feeIncomeGl) {
                    $entries[] = $this->entry($arBatch->id, $feeIncomeGl->id, 'debit', $feeAmount, 'Reversal — fee income reversed');
                    $entries[] = $this->entry($arBatch->id, $deferredFeeGl->id, 'credit', $feeAmount, 'Reversal — deferred fee restored');
                }
            } else {
                if ($interestAmount > 0) {
                    $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $interestAmount, 'Reversal — interest receivable restored');
                }
                if ($feeAmount > 0) {
                    $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $feeAmount, 'Reversal — fee receivable restored');
                }
            }

            arbatch_entries::insert($entries);

            $this->glPosting->postArBatch($arBatch, $staff->id);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // ── Restore balances ────────────────────────────────────────────
            $loan->increment('remaining_balance', $grossAmount);
            $customer->increment('current_balance', $grossAmount);

            if ($isMulti) {
                $loan->increment('deferred_interest', $interestAmount);
                $loan->increment('deferred_fees', $feeAmount);
            }

            // ── Re-open the schedule ────────────────────────────────────────
            $schedule->update(['status' => 'pending', 'paid_at' => null, 'gl_posted' => false]);
            $loan->update(['status' => 'disbursed']);

            $reversal = LoanRepayment::create([
                'loan_id' => $loan->id,
                'user_id' => $original->user_id,
                'repayment_schedule_id' => $schedule->id,
                'payment_amount' => -$grossAmount,
                'principal_amount' => -$principalAmount,
                'interest_amount' => -$interestAmount,
                'fee_amount' => -$feeAmount,
                'payment_date' => now(),
                'due_date' => $schedule->due_date,
                'status' => 'reversed',
                'payment_method' => $original->payment_method,
                'payment_reference' => $original->payment_reference,
                'gl_batch_reference' => $arBatch->reference,
                'transaction_type' => 'reversed',
                'reverses_repayment_id' => $original->id,
                'payment_received_by' => $staff->id,
                'verified_by' => $staff->id,
                'verified_at' => now(),
                'notes' => $reason,
            ]);

            try {
                $loan->user?->notify(new \App\Notifications\PaymentReversedNotification($reversal));
            } catch (Exception $e) {
                Log::warning('Payment reversal notification failed: '.$e->getMessage());
            }

            return $reversal;
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers — same pattern as NuPayService
    // ──────────────────────────────────────────────────────────────────────

    protected function entry(int $batchId, int $accountId, string $type, float $amount, string $desc): array
    {
        return [
            'arbatch_id' => $batchId,
            'gl_account_id' => $accountId,
            'entry_type' => $type,
            'amount' => round($amount, 2),
            'description' => $desc,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function resolveGl(string $key, int $branchId = 1): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            Log::warning("GL mapping not found: {$key}");

            return null;
        }

        $code = $mapping->account_code;

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
            ->where('branch_id', $branchId)->first();
    }
}
