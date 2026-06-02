<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanFee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoanController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $loans       = Loan::where('user_id', $user->id)->latest()->paginate(15);
        $loansapp    = LoanApplication::where('user_id', $user->id)->latest()->paginate(15);
        $disbursements = LoanDisbursement::where('status', 'waiting_for_approval')->get();

        return view('loans.index', compact('loans', 'loansapp', 'disbursements'));
    }

    public function show($id)
    {
        $loanApplication = LoanApplication::with('user')->findOrFail($id);
        return view('loans.show', compact('loanApplication'));
    }

    public function edit(Loan $loan)
    {
        $loanApplications = LoanApplication::where('status', 'approved')->get();
        $users = User::all();
        return view('loans.edit', compact('loan', 'loanApplications', 'users'));
    }

    public function update(Request $request, Loan $loan)
    {
        $request->validate([
            'loan_type'     => 'required|in:personal,home,business',
            'loan_amount'   => 'required|numeric|min:1',
            'collateral'    => 'nullable|string',
            'approved_amount' => 'required|numeric|min:0',
        ]);

        $loan->update([
            'loan_type'        => $request->loan_type,
            'loan_amount'      => $request->loan_amount,
            'collateral'       => $request->collateral,
            'approved_amount'  => $request->approved_amount,
            'remaining_balance'=> $request->approved_amount,
        ]);

        return redirect()->route('loans.index')->with('success', 'Loan updated successfully.');
    }

    public function destroy(Loan $loan)
    {
        $loan->status = 'archived';
        $loan->save();

        $this->sendLoanNotification(
            $loan,
            'Loan Archived',
            ['Your loan has been archived. Contact support if this is an error.'],
            $loan->status
        );

        return redirect()->route('loans.index')->with('success', 'Loan archived successfully.');
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

        // ── 1. Mark application approved ──────────────────────────────────────
        $loanApplication->update([
            'status'        => 'approved',
            'approval_date' => now(),
            'reason'        => $request->approval_comments,
            'reviewer_id'   => Auth::id(),          // admin who reviewed it
        ]);

        // ── 2. Load fee snapshot ──────────────────────────────────────────────
        $fee = LoanFee::where('loan_application_id', $loanApplication->id)->first();

        $interestRate   = $fee ? (float) $fee->interest_rate   : 0;
        $loanTermMonths = $loanApplication->loan_term_months   ?? 1;

        // ── 3. Get first schedule due date ────────────────────────────────────
        $firstSchedule = DB::table('repayment_schedules')
            ->where('loan_id', $loanApplication->id)
            ->where('status', 'pending')
            ->orderBy('due_date', 'asc')
            ->first();

        // ── 4. Create Loan record ─────────────────────────────────────────────
        $loan = Loan::create([
            'loan_application_id' => $loanApplication->id,
            'loan_product_id'     => $loanApplication->loan_product_id,
            'user_id'             => $loanApplication->user_id,
            'loan_type'           => $loanApplication->loan_type,
            'loan_amount'         => $loanApplication->loan_amount,
            'principal_amount'    => $loanApplication->loan_amount,
            'interest_rate'       => $interestRate,
            'loan_term'           => $loanTermMonths,
            'loan_term_months'    => $loanTermMonths,
            'collateral'          => $loanApplication->collateral,
            'approved_amount'     => $loanApplication->loan_amount,
            'status'              => 'approved',
            'approver_id'         => Auth::id(),
            'processed_at'        => now(),
            'approved_at'         => now(),
            'approval_comments'   => $request->approval_comments,
            'next_payment_date'   => $firstSchedule?->due_date,
            'installment_frequency' => 1,
            // Balances are set properly at disbursement — not here
            'remaining_balance'   => 0,
        ]);

        // ── 5. Create disbursement record (awaiting release) ──────────────────
        $customer = $loanApplication->user->customer;

        LoanDisbursement::create([
            'loan_id'          => $loan->id,
            'disbursed_amount' => $loanApplication->loan_amount,
            'status'           => 'waiting_for_approval',
            'payment_reference'=> $customer?->customer_code ?? "LOAN-{$loan->id}",
            'approver_id'      => Auth::id(),
            'created_at'       => now(),
        ]);

        // ── 6. Notify client ──────────────────────────────────────────────────
        $this->sendLoanNotification(
            $loanApplication,
            'Loan Application Approved',
            [
                'Your loan application has been approved.',
                'Your funds will be disbursed shortly — we will notify you once processed.',
            ],
            'approved'
        );

        return redirect()->route('admin.dashboard')
            ->with('success', 'Loan approved and disbursement created.');
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

        $loanApplication->update([
            'status'        => 'rejected',
            'approval_date' => now(),
            'reason'        => $request->rejection_reason,
            'reviewer_id'   => Auth::id(),
        ]);

        $loanApplication->repaymentSchedules()->update(['status' => 'rejected']);

        $this->sendLoanNotification(
            $loanApplication,
            'Loan Application Unsuccessful',
            [
                'Thank you for your application.',
                'Unfortunately we are unable to approve your request at this time.',
                'Reason: ' . $request->rejection_reason,
                'You are welcome to reapply after 30 days.',
            ],
            'rejected'
        );

        return redirect()->route('admin.loans')
            ->with('success', 'Application rejected.');
    }

    // ──────────────────────────────────────────────────────────
    // Disburse (manual trigger — separate from DisbursementService)
    // ──────────────────────────────────────────────────────────

    public function disburse(Loan $loan)
    {
        $loan->update([
            'status'        => 'disbursed',
            'disbursed_date'=> now(),
        ]);

        $this->sendLoanNotification(
            $loan,
            'Funds Disbursed',
            [
                'Your loan funds have been disbursed to your registered bank account.',
                'Please ensure sufficient funds are available on your repayment date.',
            ],
            'disbursed'
        );

        return redirect()->route('loans.index')
            ->with('success', 'Loan marked as disbursed.');
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
            array_unshift($bodyLines, 'Status: ' . ucfirst($status));
            $subject .= ' — ' . ucfirst($status);
        }

        try {
            Mail::to($model->user->email)
                ->queue(new \App\Mail\LoanNotificationMail($subject, $bodyLines, $model));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Loan notification failed: ' . $e->getMessage());
        }
    }
}
