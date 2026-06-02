<?php

namespace App\Http\Controllers;

use App\Models\LoanRepayment;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\DisbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanRepaymentController extends Controller
{
    protected DisbursementService $disbursement;

    public function __construct(DisbursementService $disbursement)
    {
        $this->disbursement = $disbursement;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Index — repayment schedule view
    // repayment_schedules.loan_id → loan_applications.id (not loans.id)
    // ──────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $query = RepaymentSchedule::with(['loanApplication.user', 'loanApplication.loan'])
            ->where('status', '!=', 'rejected')
            ->orderBy('due_date', 'asc');

        if (Auth::user()->rule_id !== 2) {
            $query->where('user_id', Auth::id());
        }

        $loanRepayments = $query->paginate(20);

        return view('loan_repayments.index', compact('loanRepayments'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Manual payment record — for cash/EFT payments outside Nu-Pay
    // Posts to GL and marks schedule as paid atomically
    // ──────────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'repayment_schedule_id' => ['required', 'exists:repayment_schedules,id'],
            'payment_amount'        => ['required', 'numeric', 'min:0.01'],
            'payment_date'          => ['required', 'date'],
            'payment_method'        => ['required', 'in:bank_transfer,cash,card,mobile_payment'],
            'payment_reference'     => ['nullable', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();

        try {
            $schedule = RepaymentSchedule::lockForUpdate()->findOrFail($validated['repayment_schedule_id']);

            if ($schedule->status === 'paid') {
                DB::rollBack();
                return back()->with('error', 'This instalment has already been paid.');
            }

            // Load the loan via the schedule's loan application
            $loanApplication = $schedule->loanApplication;
            $loan = $loanApplication?->loan;

            if (!$loan) {
                DB::rollBack();
                return back()->with('error', 'Cannot find the associated loan record.');
            }

            $customer = $loan->user->customer;

            // For multi-month loans — recognise deferred income on this payment
            if ($loan->loan_term_months > 1 && !$schedule->gl_posted) {
                $this->disbursement->recogniseInstallmentIncome($schedule->id, Auth::id());
            }

            $paymentAmount   = (float) $validated['payment_amount'];
            $principalAmount = (float) $schedule->principal_amount;
            $interestAmount  = (float) $schedule->interest_amount;
            $feeAmount       = (float) $schedule->fee_amount;

            // ── Create LoanRepayment record ───────────────────────────────────
            $repayment = LoanRepayment::create([
                'loan_id'               => $loan->id,
                'user_id'               => $loan->user_id,
                'repayment_schedule_id' => $schedule->id,
                'payment_amount'        => $paymentAmount,
                'principal_amount'      => $principalAmount,
                'interest_amount'       => $interestAmount,
                'fee_amount'            => $feeAmount,
                'payment_date'          => $validated['payment_date'],
                'due_date'              => $schedule->due_date,
                'status'                => 'paid',
                'payment_method'        => $validated['payment_method'],
                'payment_reference'     => $validated['payment_reference'],
                'notes'                 => $validated['notes'],
                'payment_received_by'   => Auth::id(),
                'transaction_type'      => 'manual',
            ]);

            // ── Update loan balance ───────────────────────────────────────────
            $loan->decrement('remaining_balance', $paymentAmount);
            $customer->decrement('current_balance', $paymentAmount);

            // ── Mark schedule row paid ────────────────────────────────────────
            $schedule->update([
                'status'    => 'paid',
                'paid_at'   => now(),
                'gl_posted' => true,
            ]);

            // ── Check if loan fully settled ───────────────────────────────────
            $unpaidCount = RepaymentSchedule::where('loan_id', $loanApplication->id)
                ->where('status', 'pending')
                ->count();

            if ($unpaidCount === 0) {
                $loan->update(['status' => 'settled']);
            }

            DB::commit();

            return redirect()->route('repaymentSchedules.index')
                ->with('success', 'Payment recorded successfully. Reference: ' . ($validated['payment_reference'] ?? $repayment->id));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual payment store failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            return back()->withInput()
                ->with('error', 'Payment failed to record: ' . $e->getMessage());
        }
    }

    public function show(LoanRepayment $loanRepayment)
    {
        return view('loan_repayments.show', compact('loanRepayment'));
    }

    public function create()
    {
        $schedules = RepaymentSchedule::whereIn('status', ['pending', 'payment_failed'])
            ->where('user_id', Auth::id())
            ->orderBy('due_date')
            ->get();

        return view('loan_repayments.create', compact('schedules'));
    }

    public function edit(LoanRepayment $loanRepayment)
    {
        return view('loan_repayments.edit', compact('loanRepayment'));
    }

    public function update(Request $request, LoanRepayment $loanRepayment)
    {
        $request->validate([
            'notes'             => ['nullable', 'string', 'max:500'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $loanRepayment->update($request->only(['notes', 'payment_reference']));

        return redirect()->route('loanrepayments.index')
            ->with('success', 'Repayment record updated.');
    }

    public function destroy(LoanRepayment $loanRepayment)
    {
        // Only allow deletion of non-posted manual records
        if ($loanRepayment->gl_batch_reference) {
            return back()->with('error', 'Posted repayment records cannot be deleted. Use a reversal.');
        }

        $loanRepayment->delete();

        return redirect()->route('loanrepayments.index')
            ->with('success', 'Repayment record removed.');
    }
}
