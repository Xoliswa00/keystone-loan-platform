<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\RepaymentSchedule;
use App\Services\AffordabilityService;
use App\Services\CustomerLimitationService;
use Illuminate\Http\Request;

class LoanApiController extends Controller
{
    public function __construct(
        protected AffordabilityService $affordability,
        protected CustomerLimitationService $limitations
    ) {}

    // ── List loans ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $loans = Loan::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'loan_amount' => (float) $l->loan_amount,
                'remaining_balance' => (float) $l->remaining_balance,
                'status' => $l->status,
                'loan_term_months' => $l->loan_term_months,
                'disbursed_date' => $l->disbursed_date,
                'next_payment_date' => $l->next_payment_date,
            ]);

        return response()->json(['data' => $loans]);
    }

    // ── List applications ─────────────────────────────────────────────────────

    public function applications(Request $request)
    {
        $apps = LoanApplication::where('user_id', $request->user()->id)
            ->with('loanfee')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'reference' => str_pad($a->id, 6, '0', STR_PAD_LEFT),
                'loan_amount' => (float) $a->loan_amount,
                'loan_type' => $a->loan_type,
                'loan_term_months' => $a->loan_term_months,
                'status' => $a->status,
                'created_at' => $a->created_at,
                'total_due' => (float) ($a->loanfee?->total_due ?? 0),
                'instalment' => $a->loanfee && $a->loan_term_months
                    ? round($a->loanfee->total_due / $a->loan_term_months, 2)
                    : 0,
            ]);

        return response()->json(['data' => $apps]);
    }

    // ── Available products ────────────────────────────────────────────────────

    public function products(Request $request)
    {
        $user = $request->user();
        $products = LoanProduct::active()->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'min_amount' => (float) $p->min_amount,
            'max_amount' => (float) $p->max_amount,
            'min_months' => $p->min_months,
            'max_months' => $p->max_months,
            'monthly_interest_rate_pct' => round($p->monthly_interest_rate * 100, 2),
            'requires_enhanced_affordability' => $p->requires_enhanced_affordability,
        ]);

        $canApply = $this->limitations->canApply($user);

        return response()->json([
            'products' => $products,
            'can_apply' => $canApply['allowed'],
            'block_gate' => $canApply['gate'],
            'block_reason' => $canApply['reason'],
            'unblock_at' => $canApply['unblock_at'],
        ]);
    }

    // ── Repayment schedule ────────────────────────────────────────────────────

    public function schedule(Request $request)
    {
        $user = $request->user();

        $schedule = RepaymentSchedule::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'paid', 'payment_failed'])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'installment_number' => $s->installment_number,
                'due_date' => $s->due_date,
                'emi_amount' => (float) $s->emi_amount,
                'principal_amount' => (float) $s->principal_amount,
                'interest_amount' => (float) $s->interest_amount,
                'fee_amount' => (float) $s->fee_amount,
                'status' => $s->status,
                'paid_at' => $s->paid_at,
                'is_overdue' => $s->status === 'pending' && now()->toDateString() > $s->due_date,
                'days_overdue' => $s->daysOverdue(),
            ]);

        return response()->json(['data' => $schedule]);
    }

    // ── Payment history ───────────────────────────────────────────────────────

    public function payments(Request $request)
    {
        $payments = LoanRepayment::where('user_id', $request->user()->id)
            ->orderBy('payment_date', 'desc')
            ->take(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'payment_date' => $r->payment_date,
                'payment_amount' => (float) $r->payment_amount,
                'principal_amount' => (float) $r->principal_amount,
                'interest_amount' => (float) $r->interest_amount,
                'fee_amount' => (float) $r->fee_amount,
                'status' => $r->status,
                'payment_method' => $r->payment_method,
                'reference' => $r->payment_reference,
                'transaction_type' => $r->transaction_type,
            ]);

        return response()->json(['data' => $payments]);
    }

    // ── Loan calculation (pre-application estimate) ───────────────────────────

    public function calculate(Request $request)
    {
        $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'months' => 'required|integer|min:1|max:12',
        ]);

        $product = LoanProduct::findOrFail($request->loan_product_id);
        $result = $product->calculateRepayment((float) $request->amount, (int) $request->months);

        return response()->json([
            'principal' => $result['principal'],
            'total_interest' => $result['total_interest'],
            'initiation_fee_incl' => $result['initiation_fee'],
            'service_fee_total' => $result['service_fee_total'],
            'total_fees' => $result['total_fees'],
            'total_due' => $result['total_due'],
            'base_instalment' => $result['base_instalment'],
            'months' => $result['months'],
            'monthly_rate_pct' => round((float) $product->monthly_interest_rate * 100, 2),
        ]);
    }
}
