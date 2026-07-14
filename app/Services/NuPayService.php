<?php

namespace App\Services;

use App\Models\arbatch;
use App\Models\arbatch_entries;
use App\Models\Customer;
use App\Models\gl_accounts;
use App\Models\glmapping;
use App\Models\import_batch;
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

    protected float $nupayFeeRate = 0.02; // 2% NuPay collection fee

    public function __construct(GLPostingService $glPosting, DisbursementService $disbursement)
    {
        $this->glPosting = $glPosting;
        $this->disbursement = $disbursement;
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

            // Update batch status only if all transactions in the batch are posted
            if ($batch) {
                $unposted = nupay_transactions_staging::where('import_ref', $txn->import_ref)
                    ->whereNull('posted_at')->count();
                if ($unposted === 0) {
                    $batch->update(['status' => 'PROCESSED']);
                }
            }

            // ── Archive to permanent nupay_transactions table ─────────────────
            nupay_transaction::updateOrCreate(
                ['import_ref' => $txn->import_ref, 'mandate_id' => $txn->mandate_id],
                [
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

        $isMulti = ($loan->loan_term_months ?? 1) > 1;
        $entries = [];

        // 1. Dr Bank — cash received
        $entries[] = $this->entry($arBatch->id, $bankGl->id, 'debit', $grossAmount,
            "NuPay payment received — {$txn->debtor_id} #{$txn->id}");

        // 2. Cr Loans Receivable — principal portion reduces the receivable
        $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $principalAmount,
            "Principal repayment — loan #{$loan->id} instalment #{$schedule->installment_number}");

        // 3. Income recognition — deferred vs immediate
        if ($isMulti) {
            // Release deferred interest this period
            if ($interestAmount > 0 && $deferredIntGl && $intIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $deferredIntGl->id, 'debit', $interestAmount,
                    "Deferred interest released — instalment #{$schedule->installment_number}");
                $entries[] = $this->entry($arBatch->id, $intIncomeGl->id, 'credit', $interestAmount,
                    "Interest income recognised — instalment #{$schedule->installment_number}");
            }
            // Release deferred fees this period
            if ($feeAmount > 0 && $deferredFeeGl && $feeIncomeGl) {
                $entries[] = $this->entry($arBatch->id, $deferredFeeGl->id, 'debit', $feeAmount,
                    "Deferred fee released — instalment #{$schedule->installment_number}");
                $entries[] = $this->entry($arBatch->id, $feeIncomeGl->id, 'credit', $feeAmount,
                    "Fee income recognised — instalment #{$schedule->installment_number}");
            }
        } else {
            // Single-month: income was already recognised at disbursement
            // We just need to balance the credits
            if ($interestAmount > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $interestAmount,
                    "Interest receivable cleared — loan #{$loan->id}");
            }
            if ($feeAmount > 0) {
                $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'credit', $feeAmount,
                    "Fee receivable cleared — loan #{$loan->id}");
            }
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
        $loan->decrement('remaining_balance', $grossAmount);
        $customer->decrement('current_balance', $grossAmount);

        if ($isMulti) {
            $loan->decrement('deferred_interest', $interestAmount);
            $loan->decrement('deferred_fees', $feeAmount);
        }

        // ── Mark schedule paid ──────────────────────────────────────────────────
        $schedule->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gl_posted' => true,
        ]);

        // ── Check if all schedules are paid → settle the loan ───────────────────
        $unpaidCount = RepaymentSchedule::where('loan_id', $loan->loan_application_id)
            ->where('status', 'pending')
            ->count();

        $loan->update([
            'status' => $unpaidCount === 0 ? 'settled' : 'disbursed',
        ]);

        // ── LoanRepayment audit record ───────────────────────────────────────────
        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'repayment_schedule_id' => $schedule->id,
            'nupay_staging_id' => $txn->id,
            'payment_amount' => $grossAmount,
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'fee_amount' => $feeAmount,
            'nupay_fee' => $nupayFee,
            'payment_date' => Carbon::parse($txn->action_date),
            'due_date' => $schedule->due_date,
            'status' => 'paid',
            'payment_method' => 'nupay',
            'payment_reference' => $txn->mandate_id,
            'gl_batch_reference' => $arBatch->reference,
            'transaction_type' => 'success',
        ]);

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

        $grossAmount = round($txn->instalment_amount, 2);
        $principalAmount = round($allocation['principal'], 2);
        $interestAmount = round($allocation['interest'], 2);
        $feeAmount = round($allocation['fee'], 2);

        $branchId = $loan->branch_id ?? 1;
        $locationCode = $loan->location_code ?? '000';

        $bankGl = $this->resolveGl('loan_repayment_dr', $branchId, $locationCode);
        $loanReceivGl = $this->resolveGl('loan_disbursement_dr', $branchId, $locationCode);
        $intIncomeGl = $this->resolveGl('interest_income_cr', $branchId, $locationCode);
        $feeIncomeGl = $this->resolveGl('fee_income_cr', $branchId, $locationCode);
        $deferredIntGl = $this->resolveGl('deferred_interest_cr', $branchId, $locationCode);
        $deferredFeeGl = $this->resolveGl('deferred_fee_cr', $branchId, $locationCode);

        $isMulti = ($loan->loan_term_months ?? 1) > 1;
        $entries = [];

        // Reversal = swap Dr/Cr from the original success entries
        $entries[] = $this->entry($arBatch->id, $bankGl->id, 'credit', $grossAmount,
            "Reversal — bank #{$txn->id}");
        $entries[] = $this->entry($arBatch->id, $loanReceivGl->id, 'debit', $principalAmount,
            "Reversal — principal re-opened #{$txn->id}");

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

        // Restore balances
        $loan->increment('remaining_balance', $grossAmount);
        $customer->increment('current_balance', $grossAmount);

        if ($isMulti) {
            $loan->increment('deferred_interest', $interestAmount);
            $loan->increment('deferred_fees', $feeAmount);
        }

        // Re-open the schedule
        $schedule->update([
            'status' => 'pending',
            'paid_at' => null,
            'gl_posted' => false,
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
            'payment_date' => Carbon::parse($txn->action_date),
            'due_date' => $schedule->due_date,
            'status' => 'reversed',
            'payment_method' => 'nupay',
            'payment_reference' => $txn->mandate_id,
            'gl_batch_reference' => $arBatch->reference,
            'transaction_type' => 'reversed',
            'notes' => 'Payment reversed by NuPay.',
        ]);

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
