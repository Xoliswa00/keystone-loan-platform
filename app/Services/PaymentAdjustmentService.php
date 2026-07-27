<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PaymentAdjustment;
use App\Models\RepaymentSchedule;

/**
 * Lifecycle logic for the shortfall/credit tolerance band — created when
 * ManualPaymentService/NuPayService accept a payment that's short or over
 * by up to LendingSetting::payment_tolerance_pct. GL truthfulness is
 * preserved throughout: this service never posts GL itself — it only
 * tracks who-owes-whom-what, and returns amounts for the caller to fold
 * into its own single AR batch alongside the real cash entries, so every
 * payment event stays one atomic, balanced posting.
 */
class PaymentAdjustmentService
{
    // ── Recording ────────────────────────────────────────────────────────

    public function recordShortfall(
        Customer $customer,
        Loan $loan,
        RepaymentSchedule $schedule,
        LoanRepayment $repayment,
        float $amount,
        ?int $staffId = null
    ): PaymentAdjustment {
        $amount = round($amount, 2);

        $adjustment = PaymentAdjustment::create([
            'customer_id' => $customer->id,
            'source_loan_id' => $loan->id,
            'source_repayment_schedule_id' => $schedule->id,
            'source_loan_repayment_id' => $repayment->id,
            'type' => 'shortfall',
            'original_amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => 'outstanding',
            'created_by' => $staffId,
        ]);

        AuditLog::record('payment_adjustment.shortfall_recorded', $adjustment, [], [
            'amount' => $amount,
            'source_loan_id' => $loan->id,
            'source_repayment_schedule_id' => $schedule->id,
        ], "Instalment #{$schedule->installment_number} short by R{$amount}.");

        return $adjustment;
    }

    public function recordCredit(
        Customer $customer,
        Loan $loan,
        RepaymentSchedule $schedule,
        LoanRepayment $repayment,
        float $amount,
        ?int $staffId = null
    ): PaymentAdjustment {
        $amount = round($amount, 2);

        $adjustment = PaymentAdjustment::create([
            'customer_id' => $customer->id,
            'source_loan_id' => $loan->id,
            'source_repayment_schedule_id' => $schedule->id,
            'source_loan_repayment_id' => $repayment->id,
            'type' => 'credit',
            'original_amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => 'outstanding',
            'created_by' => $staffId,
        ]);

        AuditLog::record('payment_adjustment.credit_recorded', $adjustment, [], [
            'amount' => $amount,
            'source_loan_id' => $loan->id,
            'source_repayment_schedule_id' => $schedule->id,
        ], "Instalment #{$schedule->installment_number} overpaid by R{$amount}.");

        return $adjustment;
    }

    // ── Aggregation — used by every visibility surface ──────────────────

    public function outstandingShortfall(Customer $customer): float
    {
        return (float) PaymentAdjustment::where('customer_id', $customer->id)
            ->outstanding()->shortfalls()->sum('outstanding_amount');
    }

    public function outstandingCredit(Customer $customer): float
    {
        return (float) PaymentAdjustment::where('customer_id', $customer->id)
            ->outstanding()->credits()->sum('outstanding_amount');
    }

    // ── Consumption — apply outstanding credit to an upcoming schedule ───

    /**
     * Draws down outstanding credit (oldest-first, across ANY of the
     * customer's loans, by design) against up to $amountNeeded of a
     * schedule about to be paid. Returns how much was applied (never more
     * than $amountNeeded, never more than what's available) — the caller
     * subtracts this from what it otherwise requires in cash, and folds a
     * single `Dr client_credit_balance_cr` entry for the returned amount
     * into its own AR batch (this method posts no GL itself, so every
     * payment event stays one atomic batch rather than being split across
     * two).
     */
    public function consumeCreditForSchedule(Customer $customer, RepaymentSchedule $schedule, float $amountNeeded): float
    {
        if ($amountNeeded <= 0) {
            return 0.0;
        }

        $credits = PaymentAdjustment::where('customer_id', $customer->id)
            ->outstanding()->credits()
            ->lockForUpdate()
            ->orderBy('created_at')
            ->get();

        if ($credits->isEmpty()) {
            return 0.0;
        }

        $remainingNeeded = round($amountNeeded, 2);
        $totalApplied = 0.0;

        foreach ($credits as $credit) {
            if ($remainingNeeded <= 0) {
                break;
            }

            $available = (float) $credit->outstanding_amount;
            if ($available <= 0) {
                continue;
            }

            $applied = round(min($available, $remainingNeeded), 2);
            $newOutstanding = round($available - $applied, 2);

            $old = ['outstanding_amount' => $available, 'status' => $credit->status];

            $credit->update([
                'outstanding_amount' => max(0, $newOutstanding),
                'status' => $newOutstanding <= 0 ? 'applied' : 'partially_applied',
                'applied_to_schedule_id' => $schedule->id,
            ]);

            AuditLog::record('payment_adjustment.credit_consumed', $credit, $old, [
                'applied' => $applied,
                'schedule_id' => $schedule->id,
            ], "Applied to instalment #{$schedule->installment_number}.");

            $remainingNeeded = round($remainingNeeded - $applied, 2);
            $totalApplied += $applied;
        }

        return round($totalApplied, 2);
    }

    // ── Roll-in at new-loan approval ─────────────────────────────────────

    /**
     * Disclosed-note approach (confirmed): does NOT touch the new loan's
     * own schedule, principal, or affordability assessment — it only marks
     * the outstanding shortfall(s) as rolled in and stamps the new loan's
     * carried_forward_shortfall so agreement documents/dashboard/statement
     * can disclose it as its own line item. Collection of the shortfall
     * itself continues against its ORIGINAL source loan.
     */
    public function rollShortfallIntoLoan(Customer $customer, Loan $newLoan, int $staffId): float
    {
        $shortfalls = PaymentAdjustment::where('customer_id', $customer->id)
            ->outstanding()->shortfalls()
            ->lockForUpdate()
            ->get();

        if ($shortfalls->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($shortfalls as $shortfall) {
            $amount = (float) $shortfall->outstanding_amount;

            $shortfall->update([
                'status' => 'rolled_into_loan',
                'applied_to_loan_id' => $newLoan->id,
            ]);

            AuditLog::record('payment_adjustment.rolled_into_loan', $shortfall, [], [
                'new_loan_id' => $newLoan->id,
                'amount' => $amount,
            ], "Carried forward to new loan #{$newLoan->id} at approval.");

            $total += $amount;
        }

        $total = round($total, 2);
        $newLoan->update(['carried_forward_shortfall' => $total]);

        AuditLog::record('loan.shortfall_rolled_in', $newLoan, [], [
            'carried_forward_shortfall' => $total,
        ], "Disclosed carry-forward from {$shortfalls->count()} prior shortfall(s), staff #{$staffId}.");

        return $total;
    }

    // ── Reversal ──────────────────────────────────────────────────────────

    /**
     * Voids (never deletes) the adjustment created by a partial payment
     * that's now being reversed — same non-destructive pattern the rest of
     * the reversal machinery already uses everywhere else.
     */
    public function reverseAdjustmentFor(LoanRepayment $repayment): void
    {
        $adjustment = PaymentAdjustment::where('source_loan_repayment_id', $repayment->id)
            ->outstanding()
            ->lockForUpdate()
            ->first();

        if (! $adjustment) {
            return;
        }

        $old = ['status' => $adjustment->status, 'outstanding_amount' => (float) $adjustment->outstanding_amount];

        $adjustment->update([
            'status' => 'settled',
            'outstanding_amount' => 0,
            'notes' => trim(($adjustment->notes ?? '').' Voided — source payment reversed.'),
        ]);

        AuditLog::record('payment_adjustment.reversed', $adjustment, $old, [
            'status' => 'settled',
            'outstanding_amount' => 0,
        ], "Source payment #{$repayment->id} was reversed.");
    }
}
