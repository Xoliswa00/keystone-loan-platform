<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Services\LoanAgreementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ClientStatementController extends Controller
{
    /**
     * Client downloads their own statement.
     */
    public function myStatement()
    {
        return $this->buildStatement(Auth::user());
    }

    /**
     * Admin downloads a statement for any client.
     */
    public function download(User $user)
    {
        if (!Auth::user()->hasRole('admin', 'loan_officer', 'finance')) {
            abort(403);
        }

        return $this->buildStatement($user);
    }

    protected function buildStatement(User $user)
    {
        $company = \Illuminate\Support\Facades\DB::table('companies')->first();

        // All active and historical loans
        $loans = Loan::where('user_id', $user->id)
            ->with(['loanApplication.loanfee', 'repaymentSchedules'])
            ->orderBy('created_at', 'desc')
            ->get();

        // All repayment transactions
        $repayments = LoanRepayment::where('user_id', $user->id)
            ->orderBy('payment_date', 'desc')
            ->get();

        // Current outstanding
        $totalOutstanding = $loans->whereIn('status', ['disbursed', 'payment_failed'])
            ->sum('remaining_balance');

        // Next due instalment
        $nextDue = RepaymentSchedule::where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        $data = [
            'user'             => $user,
            'customer'         => $user->customer,
            'loans'            => $loans,
            'repayments'       => $repayments,
            'totalOutstanding' => $totalOutstanding,
            'nextDue'          => $nextDue,
            'statementDate'    => now()->format('d F Y'),
            'statementRef'     => 'STMT-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('Ymd'),
            'company'          => $company,
            'ncr_number'       => $company?->ncr_number ?? 'NCRCP XXXXX',
        ];

        $pdf = Pdf::loadView('statements.account_statement', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);

        $filename = 'KCP-Statement-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('Y-m') . '.pdf';

        return $pdf->download($filename);
    }
}
