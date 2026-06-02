<?php

namespace App\Http\Controllers;

use App\Models\LoanDisbursement;
use App\Models\Loan;
use App\Services\DisbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DisbursementController extends Controller
{
    protected DisbursementService $disbursementService;

    public function __construct(DisbursementService $disbursementService)
    {
        $this->disbursementService = $disbursementService;
    }

    public function index()
    {
        $disbursements = LoanDisbursement::with(['loan', 'loan.user', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('disbursements.index', compact('disbursements'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Approve — calls DisbursementService which posts GL, initialises balances
    // ──────────────────────────────────────────────────────────────────────────

    public function approve($id)
    {
        try {
            $disbursement = $this->disbursementService->approveAndPost($id, Auth::id());

            $loanApplication = $disbursement->loan->loanApplication;

            if ($loanApplication) {
                $this->notify(
                    $loanApplication,
                    'Funds Disbursed',
                    [
                        'Your loan funds have been disbursed to your registered bank account.',
                        'Amount: R' . number_format($disbursement->disbursed_amount, 2),
                        'Please ensure sufficient funds are available on your repayment date.',
                    ]
                );
            }

            return redirect()->back()
                ->with('success', 'Disbursement approved and posted to GL.');

        } catch (\Exception $e) {
            Log::error('Disbursement approve failed', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to approve disbursement: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reject
    // ──────────────────────────────────────────────────────────────────────────

    public function reject(Request $request, $id)
    {
        $disbursement = LoanDisbursement::findOrFail($id);

        if ($disbursement->status !== 'waiting_for_approval') {
            return back()->with('error', 'This disbursement cannot be rejected in its current state.');
        }

        $disbursement->update([
            'status'           => 'rejected',
            'rejected_by'      => Auth::id(),
            'rejected_at'      => now(),
            'rejection_reason' => $request->input('rejection_reason', 'No reason provided'),
        ]);

        // Notify via loan → loanApplication chain
        $loanApplication = $disbursement->loan?->loanApplication;

        if ($loanApplication) {
            $this->notify(
                $loanApplication,
                'Disbursement Rejected',
                [
                    "Your disbursement request (#{$disbursement->id}) has been rejected.",
                    'Reason: ' . $disbursement->rejection_reason,
                    'Please contact support for assistance.',
                ]
            );
        }

        return back()->with('success', 'Disbursement rejected.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Release — only applicable if approve() was called separately from release
    // In the standard flow, approveAndPost() sets status to 'released' directly.
    // This action is for admin override of an edge case.
    // ──────────────────────────────────────────────────────────────────────────

    public function release($id)
    {
        $disbursement = LoanDisbursement::findOrFail($id);

        if ($disbursement->status !== 'approved') {
            return back()->with('error', 'Only approved disbursements can be released.');
        }

        $disbursement->update([
            'status'      => 'released',
            'released_by' => Auth::id(),
            'released_at' => now(),
        ]);

        $loanApplication = $disbursement->loan?->loanApplication;

        if ($loanApplication) {
            $this->notify(
                $loanApplication,
                'Funds Released',
                [
                    "Your loan funds (#{$disbursement->id}) have been released.",
                    'Amount: R' . number_format($disbursement->disbursed_amount, 2),
                ]
            );
        }

        return back()->with('success', 'Disbursement released.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Approve all — processes each through DisbursementService (GL posted)
    // ──────────────────────────────────────────────────────────────────────────

    public function approveAll()
    {
        $pending = LoanDisbursement::where('status', 'waiting_for_approval')->get();

        if ($pending->isEmpty()) {
            return back()->with('error', 'No pending disbursements.');
        }

        $succeeded = 0;
        $failed    = [];

        foreach ($pending as $disbursement) {
            try {
                $this->disbursementService->approveAndPost($disbursement->id, Auth::id());
                $succeeded++;

                $loanApp = $disbursement->loan?->loanApplication;
                if ($loanApp) {
                    $this->notify($loanApp, 'Funds Disbursed', [
                        'Your loan funds have been disbursed.',
                        'Amount: R' . number_format($disbursement->disbursed_amount, 2),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("approveAll failed for disbursement #{$disbursement->id}", [
                    'error' => $e->getMessage(),
                ]);
                $failed[] = "#{$disbursement->id}: " . $e->getMessage();
            }
        }

        $msg = "{$succeeded} disbursement(s) approved and posted.";
        if (!empty($failed)) {
            $msg .= ' Failures: ' . implode('; ', $failed);
        }

        return back()->with($failed ? 'error' : 'success', $msg);
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function notify($application, string $subject, array $lines): void
    {
        try {
            Mail::to($application->user->email)
                ->queue(new \App\Mail\LoanNotificationMail($subject, $lines, $application));
        } catch (\Exception $e) {
            Log::warning('Disbursement notification failed: ' . $e->getMessage());
        }
    }
}
