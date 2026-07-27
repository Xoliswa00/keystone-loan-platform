<?php

namespace App\Http\Controllers;

use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Services\ManualPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoanRepaymentController extends Controller
{
    protected ManualPaymentService $manualPayment;

    public function __construct(ManualPaymentService $manualPayment)
    {
        $this->manualPayment = $manualPayment;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Index — repayment schedule view
    // repayment_schedules.loan_id → loan_applications.id (not loans.id)
    // ──────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $query = RepaymentSchedule::with(['loanApplication.user', 'loanApplication.loan', 'paidRepayment'])
            ->where('status', '!=', 'rejected')
            ->orderBy('due_date', 'asc');

        $staffRoles = ['admin', 'finance', 'it_admin', 'loan_officer'];
        $currentRole = Auth::user()->system_role ?? (Auth::user()->rule_id === 2 ? 'admin' : 'client');

        if (! in_array($currentRole, $staffRoles)) {
            $query->where('user_id', Auth::id());
        }

        $loanRepayments = $query->paginate(20);

        return view('loan_repayments.index', compact('loanRepayments'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Manual payment record — staff directly recording a cash/EFT payment on
    // a client's behalf. Staff's own action is the attestation, so this
    // posts immediately via ManualPaymentService::recordAndPost() (real GL
    // batch + balances + schedule), same posting path client-submitted
    // proof-of-payment goes through once staff approve it (see
    // approveManualPayment() below).
    // ──────────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'repayment_schedule_id' => ['required', 'exists:repayment_schedules,id'],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:bank_transfer,cash,card,mobile_payment'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule = RepaymentSchedule::findOrFail($validated['repayment_schedule_id']);

        try {
            $repayment = $this->manualPayment->recordAndPost($schedule, $validated, $request->user());

            return redirect()->route('repaymentSchedules.index')
                ->with('success', 'Payment recorded successfully. Reference: '.($validated['payment_reference'] ?? $repayment->id));
        } catch (\Exception $e) {
            Log::error('Manual payment store failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Payment failed to record: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Client: submit proof of payment for a manual payment that didn't come
    // through NuPay — sits pending until staff verify it (see
    // approveManualPayment()/rejectManualPayment() below).
    // ──────────────────────────────────────────────────────────────────────────

    public function submitProof(Request $request, RepaymentSchedule $repaymentSchedule)
    {
        abort_unless(
            $repaymentSchedule->loanApplication?->user_id === Auth::id(),
            403
        );
        abort_unless(in_array($repaymentSchedule->status, ['pending', 'payment_failed'], true), 404);

        $validated = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof_of_payment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $validated['proof_of_payment_path'] = $request->file('proof_of_payment')
            ->store("proof_of_payment/{$request->user()->id}", 'local');

        $this->manualPayment->submitForReview($repaymentSchedule, $request->user(), $validated);

        return back()->with('success', 'Proof of payment submitted — our team will review it shortly.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Staff: verification queue for client-submitted proof of payment
    // ──────────────────────────────────────────────────────────────────────────

    public function pendingVerification()
    {
        $pendingPayments = LoanRepayment::where('status', 'pending_review')
            ->with(['user', 'repaymentSchedule', 'submittedBy'])
            ->orderBy('created_at')
            ->paginate(20);

        return view('admin.manual_payments.index', compact('pendingPayments'));
    }

    public function approveManualPayment(LoanRepayment $loanRepayment)
    {
        abort_unless($loanRepayment->status === 'pending_review', 404);

        try {
            $this->manualPayment->recordAndPost(
                $loanRepayment->repaymentSchedule,
                [
                    'payment_amount' => (float) $loanRepayment->payment_amount,
                    'payment_date' => $loanRepayment->payment_date,
                    'payment_method' => $loanRepayment->payment_method,
                    'payment_reference' => $loanRepayment->payment_reference,
                    'notes' => $loanRepayment->notes,
                ],
                Auth::user(),
                $loanRepayment
            );

            return back()->with('success', 'Payment verified and posted.');
        } catch (\Exception $e) {
            Log::error('Manual payment approval failed', ['loan_repayment_id' => $loanRepayment->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not verify this payment: '.$e->getMessage());
        }
    }

    public function rejectManualPayment(Request $request, LoanRepayment $loanRepayment)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->manualPayment->reject($loanRepayment, Auth::user(), $validated['rejection_reason']);

            return back()->with('success', 'Payment claim rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Undo an already-posted payment that turns out to have been verified/
     * recorded in error — see ManualPaymentService::reversePayment(). Works
     * regardless of the payment's origin (manual or NuPay).
     */
    public function reversePayment(Request $request, LoanRepayment $loanRepayment)
    {
        $validated = $request->validate([
            'reversal_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->manualPayment->reversePayment($loanRepayment, Auth::user(), $validated['reversal_reason']);

            return back()->with('success', 'Payment reversed.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(LoanRepayment $loanRepayment)
    {
        $staffRoles = ['admin', 'finance', 'it_admin', 'loan_officer'];
        $currentRole = Auth::user()->system_role ?? (Auth::user()->rule_id === 2 ? 'admin' : 'client');

        abort_unless(
            in_array($currentRole, $staffRoles) || $loanRepayment->user_id === Auth::id(),
            403
        );

        return view('loan_repayments.show', compact('loanRepayment'));
    }

    /**
     * Staff-only (see routes/web.php) — picks any customer's pending
     * schedule to record a manual payment against.
     */
    public function create()
    {
        $schedules = RepaymentSchedule::whereIn('status', ['pending', 'payment_failed'])
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
            'notes' => ['nullable', 'string', 'max:500'],
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
