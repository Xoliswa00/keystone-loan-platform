<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\AffordabilityService;
use App\Services\CustomerLimitationService;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function __construct(
        protected AffordabilityService $affordability,
        protected CustomerLimitationService $limitations
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();

        $activeLoan = Loan::where('user_id', $user->id)
            ->whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->first();

        $nextPayment = $activeLoan
            ? RepaymentSchedule::where('loan_id', $activeLoan->loan_application_id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->first()
            : null;

        $affordability = $this->affordability->calculate($user);
        $canApply = $this->limitations->canApply($user);

        return response()->json([
            'outstanding_balance' => (float) ($user->customer?->current_balance ?? 0),
            'active_loan' => $activeLoan ? [
                'id' => $activeLoan->id,
                'loan_amount' => (float) $activeLoan->loan_amount,
                'remaining_balance' => (float) $activeLoan->remaining_balance,
                'status' => $activeLoan->status,
                'loan_term_months' => $activeLoan->loan_term_months,
            ] : null,
            'next_payment' => $nextPayment ? [
                'due_date' => $nextPayment->due_date,
                'amount' => (float) $nextPayment->emi_amount,
                'is_overdue' => now()->toDateString() > $nextPayment->due_date,
            ] : null,
            'can_apply' => $canApply['allowed'],
            'apply_block_reason' => $canApply['reason'],
            'apply_unblock_at' => $canApply['unblock_at'],
            'max_instalment' => (float) ($affordability['max_instalment'] ?? 0),
            'profile_complete_pct' => $this->affordability->profileStatus($user)['percentage'],
        ]);
    }
}
