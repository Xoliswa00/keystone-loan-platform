<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CloDecision;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanFee;
use App\Models\PopiaConsent;
use App\Services\LoanReversalService;
use App\Services\PaymentAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoanController extends Controller
{
    protected LoanReversalService $reversal;

    protected PaymentAdjustmentService $paymentAdjustments;

    public function __construct(LoanReversalService $reversal, PaymentAdjustmentService $paymentAdjustments)
    {
        $this->reversal = $reversal;
        $this->paymentAdjustments = $paymentAdjustments;
    }

    public function index()
    {
        $user = Auth::user();

        $loans = Loan::where('user_id', $user->id)->latest()->paginate(15);
        $loansapp = LoanApplication::where('user_id', $user->id)->latest()->paginate(15);
        $disbursements = LoanDisbursement::where('status', 'waiting_for_approval')->get();

        return view('loans.index', compact('loans', 'loansapp', 'disbursements'));
    }

    public function show($id)
    {
        $loanApplication = LoanApplication::with('user')->findOrFail($id);

        return view('loans.show', compact('loanApplication'));
    }

    public function update(Request $request, Loan $loan)
    {
        $request->validate([
            'loan_type' => 'required|in:personal,home,business',
            'loan_amount' => 'required|numeric|min:1',
            'collateral' => 'nullable|string',
            'approved_amount' => 'required|numeric|min:0',
        ]);

        $loan->update([
            'loan_type' => $request->loan_type,
            'loan_amount' => $request->loan_amount,
            'collateral' => $request->collateral,
            'approved_amount' => $request->approved_amount,
            'remaining_balance' => $request->approved_amount,
        ]);

        return redirect()->route('loans.index')->with('success', 'Loan updated successfully.');
    }

    // ──────────────────────────────────────────────────────────
    // Approve — creates Loan record from an approved application
    // ──────────────────────────────────────────────────────────

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approval_comments' => 'nullable|string|max:1000',
        ]);

        $loanApplication = LoanApplication::findOrFail($id);

        try {
            $this->approveApplication($loanApplication, $request->approval_comments);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Loan approved and disbursement created.');
    }

    /**
     * Undo an approval that turns out to have been a mistake — see
     * LoanReversalService for the branching (cheap undo pre-disbursement,
     * full GL reversal post-disbursement, blocked once repayments exist).
     */
    public function reverseApproval(Request $request, $id)
    {
        $request->validate([
            'reversal_reason' => 'required|string|max:1000',
        ]);

        $loanApplication = LoanApplication::findOrFail($id);

        try {
            $this->reversal->reverseApproval($loanApplication, $request->user(), $request->reversal_reason);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Approval reversed — application is back under review.');
    }

    /**
     * Bulk-approve several pending applications in one request. Reuses
     * approveApplication() so bulk and single approval share one code path.
     * Each row is isolated — one failure doesn't block the rest.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:loan_applications,id',
            'approval_comments' => 'nullable|string|max:1000',
        ]);

        $succeeded = 0;
        $failed = [];

        foreach ($request->application_ids as $id) {
            try {
                $loanApplication = LoanApplication::findOrFail($id);

                if ($loanApplication->status !== 'pending' && $loanApplication->status !== 'under_review') {
                    $failed[] = "#{$id} (already {$loanApplication->status})";

                    continue;
                }

                $this->approveApplication($loanApplication, $request->approval_comments ?? 'Bulk approved.');
                $succeeded++;
            } catch (\Exception $e) {
                $failed[] = "#{$id} ({$e->getMessage()})";
            }
        }

        $message = "{$succeeded} application(s) approved.";
        if ($failed) {
            $message .= ' Failed: '.implode(', ', $failed);
        }

        return redirect()->route('admin.applications.index')
            ->with($failed ? 'error' : 'success', $message);
    }

    protected function approveApplication(LoanApplication $loanApplication, ?string $comments): void
    {
        // Re-fetch under a row lock rather than trusting the caller's
        // instance — a double-click or two near-simultaneous requests (this
        // method has no idempotency guard otherwise) would each pass every
        // check below and each create their own Loan + LoanDisbursement row
        // for the same application. The lock serialises the second request
        // behind the first; by the time it acquires the lock, status is
        // already 'approved' and the check below stops it.
        $loanApplication = DB::transaction(function () use ($loanApplication, $comments) {
            $loanApplication = LoanApplication::lockForUpdate()->findOrFail($loanApplication->id);

            if (! in_array($loanApplication->status, ['pending', 'under_review'], true)) {
                throw new \RuntimeException("Application #{$loanApplication->id} is already {$loanApplication->status} — nothing to approve.");
            }

            // CloDecisionEngine's own consent check is advisory-only (forces
            // REVIEW, doesn't block) — this is the actual hard gate. Credit
            // assessment and reporting cannot lawfully proceed without both
            // consents currently in force, so a withdrawn consent must stop
            // approval outright, not just flag it for a human to notice.
            if (! PopiaConsent::isGranted($loanApplication->user_id, 'data_processing')
                || ! PopiaConsent::isGranted($loanApplication->user_id, 'credit_bureau_check')) {
                throw new \RuntimeException('Cannot approve — applicant does not currently have POPIA data-processing and credit-bureau consent in force.');
            }

            // ── 1. Mark application approved ──────────────────────────────────
            $loanApplication->update([
                'status' => 'approved',
                'approval_date' => now(),
                'reason' => $comments,
                'reviewer_id' => Auth::id(),          // admin who reviewed it
            ]);

            $this->logCloOverrideIfAny($loanApplication, 'APPROVE', $comments);

            // ── 2. Load fee snapshot ────────────────────────────────────────────
            $fee = LoanFee::where('loan_application_id', $loanApplication->id)->first();

            $interestRate = $fee ? (float) $fee->interest_rate : 0;
            $loanTermMonths = $loanApplication->loan_term_months ?? 1;

            // ── 3. Get first schedule due date ──────────────────────────────────
            $firstSchedule = DB::table('repayment_schedules')
                ->where('loan_id', $loanApplication->id)
                ->where('status', 'pending')
                ->orderBy('due_date', 'asc')
                ->first();

            // ── 4. Create Loan record ───────────────────────────────────────────
            $loan = Loan::create([
                'loan_application_id' => $loanApplication->id,
                'loan_product_id' => $loanApplication->loan_product_id,
                'user_id' => $loanApplication->user_id,
                'loan_type' => $loanApplication->loan_type,
                'loan_amount' => $loanApplication->loan_amount,
                'principal_amount' => $loanApplication->loan_amount,
                'interest_rate' => $interestRate,
                'loan_term' => $loanTermMonths,
                'loan_term_months' => $loanTermMonths,
                'collateral' => $loanApplication->collateral,
                'approved_amount' => $loanApplication->loan_amount,
                'status' => 'approved',
                'approver_id' => Auth::id(),
                'processed_at' => now(),
                'approved_at' => now(),
                'approval_comments' => $comments,
                'next_payment_date' => $firstSchedule?->due_date,
                'installment_frequency' => 1,
                // Balances are set properly at disbursement — not here
                'remaining_balance' => 0,
            ]);

            // ── 5. Create disbursement record (awaiting release) ────────────────
            $customer = $loanApplication->user->customer;

            LoanDisbursement::create([
                'loan_id' => $loan->id,
                'disbursed_amount' => $loanApplication->loan_amount,
                'status' => 'waiting_for_approval',
                'payment_reference' => $customer?->customer_code ?? "LOAN-{$loan->id}",
                'approver_id' => Auth::id(),
                'created_at' => now(),
            ]);

            // Disclosed-note approach (confirmed): rolling a shortfall in
            // does NOT touch this new loan's own schedule/principal/
            // affordability — it only marks the shortfall(s) linked here
            // and stamps carried_forward_shortfall so agreement documents/
            // dashboard/statement can disclose it as its own line item.
            if ($customer) {
                $this->paymentAdjustments->rollShortfallIntoLoan($customer, $loan, Auth::id());
            }

            return $loanApplication;
        });

        // ── 6. Notify client ────────────────────────────────────────────────────
        // Outside the transaction — a notification failure must never roll
        // back an approval that already recorded successfully. (Also already
        // exception-safe internally — see sendLoanNotification().)
        $this->sendLoanNotification(
            $loanApplication,
            'Loan Application Approved',
            [
                'Your loan application has been approved.',
                'Your funds will be disbursed shortly — we will notify you once processed.',
            ],
            'approved'
        );
    }

    // ──────────────────────────────────────────────────────────
    // Reject
    // ──────────────────────────────────────────────────────────

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $loanApplication = LoanApplication::findOrFail($id);

        try {
            $this->rejectApplication($loanApplication, $request->rejection_reason);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.loans')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.loans')
            ->with('success', 'Application rejected.');
    }

    /**
     * Bulk-reject several pending applications with a single shared reason.
     * Reuses rejectApplication() so bulk and single rejection share one
     * code path. Each row is isolated — one failure doesn't block the rest.
     */
    public function bulkReject(Request $request)
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:loan_applications,id',
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $succeeded = 0;
        $failed = [];

        foreach ($request->application_ids as $id) {
            try {
                $loanApplication = LoanApplication::findOrFail($id);

                if ($loanApplication->status !== 'pending' && $loanApplication->status !== 'under_review') {
                    $failed[] = "#{$id} (already {$loanApplication->status})";

                    continue;
                }

                $this->rejectApplication($loanApplication, $request->rejection_reason);
                $succeeded++;
            } catch (\Exception $e) {
                $failed[] = "#{$id} ({$e->getMessage()})";
            }
        }

        $message = "{$succeeded} application(s) rejected.";
        if ($failed) {
            $message .= ' Failed: '.implode(', ', $failed);
        }

        return redirect()->route('admin.applications.index')
            ->with($failed ? 'error' : 'success', $message);
    }

    protected function rejectApplication(LoanApplication $loanApplication, string $reason): void
    {
        $loanApplication = DB::transaction(function () use ($loanApplication, $reason) {
            $loanApplication = LoanApplication::lockForUpdate()->findOrFail($loanApplication->id);

            if (! in_array($loanApplication->status, ['pending', 'under_review'], true)) {
                throw new \RuntimeException("Application #{$loanApplication->id} is already {$loanApplication->status} — nothing to reject.");
            }

            $loanApplication->update([
                'status' => 'rejected',
                'approval_date' => now(),
                'reason' => $reason,
                'reviewer_id' => Auth::id(),
            ]);

            $this->logCloOverrideIfAny($loanApplication, 'REJECT', $reason);

            $loanApplication->repaymentSchedules()->update(['status' => 'rejected']);

            return $loanApplication;
        });

        $this->sendLoanNotification(
            $loanApplication,
            'Loan Application Unsuccessful',
            [
                'Thank you for your application.',
                'Unfortunately we are unable to approve your request at this time.',
                'Reason: '.$reason,
                'You are welcome to reapply after 30 days.',
            ],
            'rejected'
        );
    }

    public function updatePayment(Loan $loan, Request $request)
    {
        $request->validate([
            'remaining_balance' => 'required|numeric|min:0',
            'next_payment_date' => 'nullable|date',
        ]);

        $loan->update([
            'remaining_balance' => $request->remaining_balance,
            'next_payment_date' => $request->next_payment_date,
        ]);

        return redirect()->route('loans.index')
            ->with('success', 'Payment details updated.');
    }

    // ──────────────────────────────────────────────────────────

    protected function sendLoanNotification($model, string $subject, array $bodyLines = [], ?string $status = null): void
    {
        if ($status) {
            array_unshift($bodyLines, 'Status: '.ucfirst($status));
            $subject .= ' — '.ucfirst($status);
        }

        try {
            Mail::to($model->user->email)
                ->queue(new \App\Mail\LoanNotificationMail($subject, $bodyLines, $model));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Loan notification failed: '.$e->getMessage());
        }
    }

    /**
     * CLO Constitution "Override Rules": if a human decision contradicts the
     * latest CLO recommendation, log it as an override with justification.
     * The CLO evaluation never blocks approve()/reject() — it is advisory only.
     */
    protected function logCloOverrideIfAny(LoanApplication $loanApplication, string $humanDecision, ?string $justification): void
    {
        $latest = CloDecision::latestFor($loanApplication->id)->first();

        if (! $latest || $latest->decision === $humanDecision) {
            return;
        }

        AuditLog::record(
            'clo_override',
            $loanApplication,
            ['clo_decision' => $latest->decision],
            ['human_decision' => $humanDecision],
            $justification ?: 'No justification provided.'
        );
    }
}
