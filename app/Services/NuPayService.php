<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\Customer;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\import_batch;
use App\Models\LendingSetting;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\nupay_transaction;
use App\Models\nupay_transactions_staging;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NuPayService
{
    protected GLPostingService $glPosting;

    protected DisbursementService $disbursement;

    protected PaymentAdjustmentService $paymentAdjustments;

    protected float $nupayFeeRate = 0.02; // 2% NuPay collection fee

    public function __construct(GLPostingService $glPosting, DisbursementService $disbursement, PaymentAdjustmentService $paymentAdjustments)
    {
        $this->glPosting = $glPosting;
        $this->disbursement = $disbursement;
        $this->paymentAdjustments = $paymentAdjustments;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Main entry point — post a single staged transaction
    // ──────────────────────────────────────────────────────────────────────────

    public function postTransaction(int $stagingId, int $userId): arbatch
    {
        return DB::transaction(function () use ($stagingId, $userId) {

            $txn = nupay_transactions_staging::lockForUpdate()->findOrFail($stagingId);
            $type = strtolower(trim($txn->transaction_type ?? 'success'));

            if ($txn->posted_at !== null) {
                throw new Exception("Transaction #{$stagingId} already posted.");
            }

            // ── Resolve customer ──────────────────────────────────────────────
            $user = User::where('ID_Number', $txn->debtor_id)->first();
            if (! $user) {
                throw new Exception("No user found for debtor_id '{$txn->debtor_id}'.");
            }

            $customer = Customer::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $customer) {
                throw new Exception("No customer record for user #{$user->id}.");
            }

            // ── AR batch header ───────────────────────────────────────────────
            $ref = 'ARB-NUPAY-'.now()->format('YmdHis').'-'.$txn->id;
            $arBatch = arbatch::create([
                'reference' => $ref,
                'customer_id' => $customer->id,
                'source_type' => nupay_transactions_staging::class,
                'source_id' => $txn->id,
                'total_amount' => $txn->instalment_amount,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // ── Dispatch by transaction type ──────────────────────────────────
            $repayment = match ($type) {
                'success' => $this->handleSuccess($txn, $arBatch, $user, $customer, $userId),
                'failed', 'canceled' => $this->handleFailed($txn, $arBatch, $customer),
                'reversed' => $this->handleReversed($txn, $arBatch, $customer),
                default => throw new Exception("Unsupported NuPay transaction type: {$type}"),
            };

            // ── Post to GL ────────────────────────────────────────────────────
            $this->glPosting->postArBatch($arBatch, $userId);
            $arBatch->update(['posted_to_gl' => true, 'status' => 'posted']);

            // ── Finalise staging record ───────────────────────────────────────
            $batch = import_batch::where('import_ref', $txn->import_ref)->first();
            $txn->update([
                'import_id' => $batch?->id,
                'posted_at' => now(),
            ]);

            // Update batch status only if all *postable* transactions are
            // posted. 'tracking' rows are deliberately excluded — they're
            // still-in-flight mandates with no resolved outcome yet (see the
            // 'default' throw above), so posted_at never gets set for them;
            // counting them here would mean a batch with any tracking rows
            // could never reach PROCESSED.
            if ($batch) {
                $unposted = nupay_transactions_staging::where('import_ref', $txn->import_ref)
                    ->whereNull('posted_at')
                    ->where('transaction_type', '!=', 'tracking')
                    ->count();
                if ($unposted === 0) {
                    $batch->update(['status' => 'PROCESSED']);
                }
            }

            // ── Archive to permanent nupay_transactions table ─────────────────
            nupay_transaction::updateOrCreate(
                ['import_ref' => $txn->import_ref, 'mandate_id' => $txn->mandate_id],
                [
                    'mandate_request_tran_id' => $txn->mandate_request_tran_id,
                    'debtor_id' => $txn->debtor_id,
                    'loan_repayment_id' => $repayment?->id,
                    'amount' => round($txn->instalment_amount, 2),
                    'fee' => round($txn->instalment_amount * $this->nupayFeeRate, 2),
                    'net_amount' => round($txn->instalment_amount * (1 - $this->nupayFeeRate), 2),
                    'transaction_type' => $type,
                    'posted_at' => now(),
                ]
            );

            return $arBatch;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SUCCESS — payment received, net against customer, recognise income
    // ──────────────────────────────────────────────────────────────────────────

    protected function handleSuccess(
        nupay_transactions_staging $txn,
        arbatch $arBatch,
        User $user,
        Customer $customer,
        int $userId
    ): LoanRepayment {

        $allocation = $this->allocate($customer, $txn);
        $loan = $allocation['loan'];
        $schedule = $allocation['schedule'];

        $grossAmount = round($txn->instalment_amount, 2);
        $principalAmount = round($allocation['principal'], 2);
        $interestAmount = round($allocation['interest'], 2);
        $feeAmount = round($allocation['fee'], 2);
        $nupayFee = round($grossAmount * $this->nupayFeeRate, 2);

        $branchId = $loan->branch_id ?? 1;
        $locationCode = $loan->location_code ?? '000';

        $bankGl = $this->resolveGl('loan_repayment_dr', $branchId, $locationCode);
        $loanReceivGl = $this->resolveGl('loan_disbursement_dr', $branchId, $locationCode);
        $feeIncomeGl = $this->resolveGl('fee_income_cr', $branchId, $locationCode);
        $intIncomeGl = $this->resolveGl('interest_income_cr', $branchId, $locationCode);
        $deferredIntGl = $this->resolveGl('deferred_interest_cr', $branchId, $locationCode);
        $deferredFeeGl = $this->resolveGl('deferred_fee_cr', $branchId, $locationCode);
        $bankChargesGl = $this->resolveGl('bank_charges', $branchId, $locationCode);

        if (! $bankGl || ! $loanReceivGl) {
            throw new Exception('Missing required GL accounts for payment posting.');
        }

        // A prior partial payment may already have cleared part of this
        // same schedule — re-derive what's still actually outstanding.
        $alreadyPaid = round((float) $schedule->amount_paid_to_date, 2);
        [, , , , $remFee, $remInterest, $remPrincipal] = $this->waterfallAllocate($alreadyPaid, $feeAmount, $interestAmount, $principalAmount);
        $remainingExpected = round($remFee + $remInterest + $remPrincipal, 2);

        // Draw down any outstanding credit (cross-loan, by design) before
        // deciding whether this NuPay collection is short/exact/over.
        $creditApplied = $this->paymentAdjustments->consumeCreditForSchedule($customer, $schedule, $remainingExpected);
        $totalCovered = round($grossAmount + $creditApplied, 2);

        // Tolerance band — the NuPay feed's instalment_amount can miss
        // what's still outstanding by up to this much (either direction —
        // a mandate typo can go either way) and still post, instead of
        // being rejected outright.
        $tolerancePct = (float) LendingSetting::current()->payment_tolerance_pct;
        $toleranceAmount = round($remainingExpected * $tolerancePct, 2);
        $diff = round($totalCovered - $remainingExpected, 2);

        if (abs($diff) > 0.01 && abs($diff) > $toleranceAmount) {
            throw new Exception(
                "NuPay instalment amount R{$grossAmount} does not match the amount still due (R{$remainingExpected}) for schedule #{$schedule->id}, ".
                'and is outside the '.($tolerancePct * 100)."% tolerance (max variance R{$toleranceAmount})."
            );
        }

        [$allocFee, $allocInterest, $allocPrincipal, $excess] = $this->waterfallAllocate($totalCovered, $remFee, $remInterest, $remPrincipal);
        $shortfallThisTxn = max(0, round($remainingExpected - $totalCovered, 2));
        $isFullyPaid = $shortfallThisTxn <= 0.01;

        $creditGl = $this->resolveGl('client_credit_balance_cr', $branchId, $locationCode);

        $isMulti = ($loan->loan_term_months ?? 1) > 1;
        $entries = [];

        // 1. Dr Bank — cash received
        $entries[] = $this->entry($arBatch->id, $bankGl->id, 'debit', $grossAmount,
            "NuPay payment received — {$txn->debtor_id} #{$txn->id}");

        if ($creditApplied > 0 && $creditGl) {
            $entries[] = $this->entry($arBatch->id, $creditGl->id, 'debit', $creditApplied,
                "Client credit applied — instalment #{$schedule->installment_number}");
        }

        // 2. Cr Loans Receivable — principal portion reduces the receivable
        if ($allocPrincipal > 0) {
            $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $allocPrincipal,
                "Principal repayment — loan #{$loan->id} instalment #{$schedule->installment_number}");
        }

        // 3. Income recognition — deferred vs immediate
        if ($isMulti) {
            // Loans Receivable was only carrying principal (see the credit
            // above) — clear the interest/fee portion of the receivable
            // too, or the batch's debit (gross) side never matches its
            // credit side for any loan with interest/fees.
            if ($allocInterest > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $allocInterest,
                    "Interest receivable cleared — instalment #{$schedule->installment_number}");
            }
            if ($allocFee > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $allocFee,
                    "Fee receivable cleared — instalment #{$schedule->installment_number}");
            }
            // Separate, self-balancing pair — recognises previously
            // deferred interest/fee income now that it's actually been
            // collected (whether via fresh cash or a credit drawdown).
            if ($allocInterest > 0 && $deferredIntGl && $intIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $deferredIntGl->id, 'debit', $allocInterest,
                    "Deferred interest released — instalment #{$schedule->installment_number}");
                $entries[] = $this->entry($arBatch->id, $intIncomeGl->id, 'credit', $allocInterest,
                    "Interest income recognised — instalment #{$schedule->installment_number}");
            }
            // Release deferred fees this period
            if ($allocFee > 0 && $deferredFeeGl && $feeIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $deferredFeeGl->id, 'debit', $allocFee,
                    "Deferred fee released — instalment #{$schedule->installment_number}");
                $entries[] = $this->entry($arBatch->id, $feeIncomeGl->id, 'credit', $allocFee,
                    "Fee income recognised — instalment #{$schedule->installment_number}");
            }
        } else {
            // Single-month: income was already recognised at disbursement
            // We just need to balance the credits
            if ($allocInterest > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $allocInterest,
                    "Interest receivable cleared — loan #{$loan->id}");
            }
            if ($allocFee > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $allocFee,
                    "Fee receivable cleared — loan #{$loan->id}");
            }
        }

        if ($excess > 0 && $creditGl) {
            $entries[] = $this->entry($arBatch->id, $creditGl->id, 'credit', $excess,
                "Overpayment — instalment #{$schedule->installment_number}");
        }

        // 4. NuPay collection fee (bank expense)
        if ($nupayFee > 0 && $bankChargesGl) {
            $entries[] = $this->entry($arBatch->id, $bankChargesGl->id, 'debit', $nupayFee,
                "NuPay collection fee — txn #{$txn->id}");
            $entries[] = $this->entry($arBatch->id, $bankGl->id, 'credit', $nupayFee,
                "NuPay fee deduction — txn #{$txn->id}");
        }

        arbatch_entries::insert($entries);

        // ── Update balances ────────────────────────────────────────────────────
        // remaining_balance/current_balance only move for FRESH cash — the
        // credit-applied portion already reduced these when it was
        // originally received as an overpayment.
        $loan->decrement('remaining_balance', $grossAmount);
        $customer->decrement('current_balance', $grossAmount);

        if ($isMulti) {
            $loan->decrement('deferred_interest', $allocInterest);
            $loan->decrement('deferred_fees', $allocFee);
        }

        // ── Mark schedule paid (or leave pending if still short) ────────────────
        $schedule->update([
            'amount_paid_to_date' => round($alreadyPaid + $totalCovered, 2),
            'partial_payment_flag' => ! $isFullyPaid,
            'status' => $isFullyPaid ? 'paid' : 'pending',
            'paid_at' => $isFullyPaid ? now() : null,
            'gl_posted' => true,
        ]);

        // ── Check if all schedules are paid → settle the loan ───────────────────
        if ($isFullyPaid) {
            $unpaidCount = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
                ->where('status', 'pending')
                ->count();

            $loan->update([
                'status' => $unpaidCount === 0 ? 'settled' : 'disbursed',
            ]);
        }

        // ── LoanRepayment audit record ───────────────────────────────────────────
        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'repayment_schedule_id' => $schedule->id,
            'nupay_staging_id' => $txn->id,
            'payment_amount' => $grossAmount,
            'principal_amount' => $allocPrincipal,
            'interest_amount' => $allocInterest,
            'fee_amount' => $allocFee,
            'credit_applied' => $creditApplied,
            'credit_created' => $excess,
            'nupay_fee' => $nupayFee,
            'payment_date' => Carbon::parse($txn->action_date),
            'due_date' => $schedule->due_date,
            'status' => $isFullyPaid ? 'paid' : 'partial',
            'payment_method' => 'nupay',
            'payment_reference' => $txn->mandate_id,
            'gl_batch_reference' => $arBatch->reference,
            'transaction_type' => 'success',
        ]);

        if ($shortfallThisTxn > 0) {
            $this->paymentAdjustments->recordShortfall($customer, $loan, $schedule, $repayment, $shortfallThisTxn, $userId);
        }
        if ($excess > 0) {
            $this->paymentAdjustments->recordCredit($customer, $loan, $schedule, $repayment, $excess, $userId);
        }

        $this->notify($user, new \App\Notifications\PaymentReceivedNotification($repayment));

        return $repayment;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FAILED / CANCELED — debit order bounced
    // ──────────────────────────────────────────────────────────────────────────

    protected function handleFailed(
        nupay_transactions_staging $txn,
        arbatch $arBatch,
        Customer $customer
    ): ?LoanRepayment {

        $allocation = $this->allocate($customer, $txn);
        $loan = $allocation['loan'];
        $schedule = $allocation['schedule'];

        $branchId = $loan->branch_id ?? 1;
        $locationCode = $loan->location_code ?? '000';

        $penaltyIncGl = $this->resolveGl('penalty_income', $branchId, $locationCode);
        $penaltyRecvGl = $this->resolveGl('penalty_receivable_dr', $branchId, $locationCode);

        $entries = [];

        // Dishonour fee — adds to what client owes
        $dishonourFee = 40.00; // Standard dishonour fee (configurable)

        if ($penaltyRecvGl && $penaltyIncGl) {
            $entries[] = $this->entry($arBatch->id, $penaltyRecvGl->id, 'debit', $dishonourFee,
                "Dishonour fee — failed debit #{$txn->id}");
            $entries[] = $this->entry($arBatch->id, $penaltyIncGl->id, 'credit', $dishonourFee,
                "Dishonour fee income — failed debit #{$txn->id}");
        }

        if (! empty($entries)) {
            arbatch_entries::insert($entries);
        }

        // Mark schedule as payment_failed — DPD clock starts from here
        $schedule->update(['status' => 'payment_failed']);

        // Add dishonour fee to outstanding balance
        if ($penaltyRecvGl) {
            $customer->increment('current_balance', $dishonourFee);
            $loan->increment('remaining_balance', $dishonourFee);
        }

        $loan->update(['status' => 'disbursed']); // stays active, not settled

        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $customer->user_id,
            'repayment_schedule_id' => $schedule->id,
            'nupay_staging_id' => $txn->id,
            'payment_amount' => 0,
            'payment_date' => Carbon::parse($txn->action_date),
            'due_date' => $schedule->due_date,
            'status' => 'payment_failed',
            'payment_method' => 'nupay',
            'payment_reference' => $txn->mandate_id,
            'gl_batch_reference' => $arBatch->reference,
            'transaction_type' => strtolower($txn->transaction_type),
            'notes' => 'Debit order failed — dishonour fee applied.',
        ]);

        $this->notify($customer->user, new \App\Notifications\PaymentFailedNotification($repayment, $dishonourFee));

        return $repayment;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REVERSED — payment that was previously successful gets reversed
    // ──────────────────────────────────────────────────────────────────────────

    protected function handleReversed(
        nupay_transactions_staging $txn,
        arbatch $arBatch,
        Customer $customer
    ): ?LoanRepayment {

        $allocation = $this->allocate($customer, $txn, allowPaid: true);
        $loan = $allocation['loan'];
        $schedule = $allocation['schedule'];

        // NuPay reversals reference the ORIGINAL LoanRepayment (found via
        // nupay_staging on the archived success txn) to know exactly how
        // much of that payment was credit-sourced vs fresh cash — the
        // reversal must swap those GL legs precisely, same as a manual
        // payment reversal.
        $originalRepayment = LoanRepayment::where('nupay_staging_id', $txn->id)
            ->whereIn('status', ['paid', 'partial'])
            ->latest()
            ->first();

        $grossAmount = round($txn->instalment_amount, 2);
        $principalAmount = round($originalRepayment->principal_amount ?? $allocation['principal'], 2);
        $interestAmount = round($originalRepayment->interest_amount ?? $allocation['interest'], 2);
        $feeAmount = round($originalRepayment->fee_amount ?? $allocation['fee'], 2);
        $creditApplied = round((float) ($originalRepayment->credit_applied ?? 0), 2);
        $creditCreated = round((float) ($originalRepayment->credit_created ?? 0), 2);

        $branchId = $loan->branch_id ?? 1;
        $locationCode = $loan->location_code ?? '000';

        $bankGl = $this->resolveGl('loan_repayment_dr', $branchId, $locationCode);
        $loanReceivGl = $this->resolveGl('loan_disbursement_dr', $branchId, $locationCode);
        $intIncomeGl = $this->resolveGl('interest_income_cr', $branchId, $locationCode);
        $feeIncomeGl = $this->resolveGl('fee_income_cr', $branchId, $locationCode);
        $deferredIntGl = $this->resolveGl('deferred_interest_cr', $branchId, $locationCode);
        $deferredFeeGl = $this->resolveGl('deferred_fee_cr', $branchId, $locationCode);
        $creditGl = $this->resolveGl('client_credit_balance_cr', $branchId, $locationCode);

        $isMulti = ($loan->loan_term_months ?? 1) > 1;
        $entries = [];

        // Reversal = swap Dr/Cr from the original success entries
        $entries[] = $this->entry($arBatch->id, $bankGl->id, 'credit', $grossAmount,
            "Reversal — bank #{$txn->id}");

        if ($creditApplied > 0 && $creditGl) {
            $entries[] = $this->entry($arBatch->id, $creditGl->id, 'credit', $creditApplied,
                "Reversal — client credit restored #{$txn->id}");
        }
        if ($creditCreated > 0 && $creditGl) {
            $entries[] = $this->entry($arBatch->id, $creditGl->id, 'debit', $creditCreated,
                "Reversal — client credit withdrawn #{$txn->id}");
        }

        $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $principalAmount,
            "Reversal — principal re-opened #{$txn->id}");

        if ($isMulti) {
            // Mirror of the receivable-clearing fix in handleSuccess() —
            // reopen the interest/fee portion of the receivable too, not
            // just principal, or this batch is unbalanced the same way.
            if ($interestAmount > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $interestAmount, 'Reversal — interest receivable restored');
            }
            if ($feeAmount > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $feeAmount, 'Reversal — fee receivable restored');
            }
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

        // Restore balances
        $loan->increment('remaining_balance', $grossAmount);
        $customer->increment('current_balance', $grossAmount);

        if ($isMulti) {
            $loan->increment('deferred_interest', $interestAmount);
            $loan->increment('deferred_fees', $feeAmount);
        }

        // Re-open the schedule — pull back only what THIS repayment
        // contributed (credit_created never touched the schedule's own
        // receivable, so it's excluded), in case other partial payments
        // against this same schedule still stand.
        $allocatedToSchedule = round($principalAmount + $interestAmount + $feeAmount, 2);
        $newAmountPaidToDate = max(0, round((float) $schedule->amount_paid_to_date - $allocatedToSchedule, 2));
        $schedule->update([
            'amount_paid_to_date' => $newAmountPaidToDate,
            'partial_payment_flag' => $newAmountPaidToDate > 0,
            'status' => 'pending',
            'paid_at' => null,
            'gl_posted' => $newAmountPaidToDate > 0,
        ]);

        $loan->update(['status' => 'disbursed']);

        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $customer->user_id,
            'repayment_schedule_id' => $schedule->id,
            'nupay_staging_id' => $txn->id,
            'payment_amount' => -$grossAmount,
            'principal_amount' => -$principalAmount,
            'interest_amount' => -$interestAmount,
            'fee_amount' => -$feeAmount,
            'credit_applied' => -$creditApplied,
            'credit_created' => -$creditCreated,
            'payment_date' => Carbon::parse($txn->action_date),
            'due_date' => $schedule->due_date,
            'status' => 'reversed',
            'payment_method' => 'nupay',
            'payment_reference' => $txn->mandate_id,
            'gl_batch_reference' => $arBatch->reference,
            'transaction_type' => 'reversed',
            'notes' => 'Payment reversed by NuPay.',
        ]);

        if ($originalRepayment) {
            $this->paymentAdjustments->reverseAdjustmentFor($originalRepayment);
        }

        $this->notify($customer->user, new \App\Notifications\PaymentReversedNotification($repayment));

        return $repayment;
    }

    /**
     * Notification failures (e.g. mail transport down) must never break the
     * payment-recording transaction they're reporting on.
     */
    protected function notify(?User $user, $notification): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Exception $e) {
            Log::warning('Payment notification failed: '.$e->getMessage());
        }
    }

    /**
     * Allocates $amount across fee/interest/principal in that priority
     * order — same helper as ManualPaymentService (duplicated, not
     * shared, matching how entry()/resolveGl() already aren't shared
     * between these two services). Returns
     * [allocFee, allocInterest, allocPrincipal, excess, remFee, remInterest, remPrincipal].
     */
    protected function waterfallAllocate(float $amount, float $fee, float $interest, float $principal): array
    {
        $remaining = round($amount, 2);

        $allocFee = round(min($remaining, $fee), 2);
        $remaining = round($remaining - $allocFee, 2);

        $allocInterest = round(min($remaining, $interest), 2);
        $remaining = round($remaining - $allocInterest, 2);

        $allocPrincipal = round(min($remaining, $principal), 2);
        $remaining = round($remaining - $allocPrincipal, 2);

        $excess = max(0, $remaining);
        $remFee = round($fee - $allocFee, 2);
        $remInterest = round($interest - $allocInterest, 2);
        $remPrincipal = round($principal - $allocPrincipal, 2);

        return [$allocFee, $allocInterest, $allocPrincipal, $excess, $remFee, $remInterest, $remPrincipal];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Allocation — find loan + matching schedule row, return splits
    // ──────────────────────────────────────────────────────────────────────────

    protected function allocate(
        Customer $customer,
        nupay_transactions_staging $txn,
        bool $allowPaid = false
    ): array {

        $loan = Loan::where('user_id', $customer->user_id)
            ->whereIn('status', ['disbursed', 'payment_failed'])
            ->lockForUpdate()
            ->first();

        if (! $loan) {
            throw new Exception("No active disbursed loan for customer #{$customer->id}.");
        }

        $scheduleQuery = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
            ->whereBetween('due_date', [
                Carbon::parse($txn->action_date)->startOfMonth(),
                Carbon::parse($txn->action_date)->endOfMonth(),
            ])
            ->lockForUpdate();

        if (! $allowPaid) {
            $scheduleQuery->whereIn('status', ['pending', 'payment_failed']);
        }

        $schedule = $scheduleQuery->first();

        if (! $schedule) {
            // Fallback: find the earliest unpaid schedule
            $schedule = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
                ->whereIn('status', ['pending', 'payment_failed'])
                ->orderBy('due_date')
                ->lockForUpdate()
                ->first();
        }

        if (! $schedule) {
            throw new Exception("No matching schedule row found for loan #{$loan->id} — {$txn->action_date}.");
        }

        // Use per-installment splits from the schedule row (set at application time)
        return [
            'loan' => $loan,
            'schedule' => $schedule,
            'principal' => (float) ($schedule->principal_amount ?? $loan->principal_amount),
            'interest' => (float) ($schedule->interest_amount ?? 0),
            'fee' => (float) ($schedule->fee_amount ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

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

    protected function resolveGl(string $key, $branchId = 1, $locationCode = '000'): ?gl_accounts
    {
        $mapping = glmapping::where('key', $key)->where('is_active', 1)->first();
        if (! $mapping) {
            Log::warning("GL mapping not found: {$key}");

            return null;
        }

        $code = $mapping->account_code;

        return gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
            ->where('branch_id', $branchId)->first()
            ?? gl_accounts::whereHas('chartOfAccount', fn ($q) => $q->where('account_code', $code))
                ->where('branch_id', 1)->first();
    }
}
