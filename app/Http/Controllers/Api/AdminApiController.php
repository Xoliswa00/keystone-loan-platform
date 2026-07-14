<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CloDecision;
use App\Models\FinancialPeriod;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\RepaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin Monitoring API — feeds the Keystone Monitor mobile app.
 * All endpoints require a staff-role Sanctum token.
 */
class AdminApiController extends Controller
{
    // ── Dashboard KPIs ────────────────────────────────────────────────────────

    public function dashboard()
    {
        return response()->json(Cache::remember('admin_api_dashboard', 60, function () {
            $now = now();

            return [
                // Pending actions
                'pending_approvals' => LoanApplication::whereIn('status', ['pending', 'under_review'])->count(),
                'pending_disbursements' => LoanDisbursement::where('status', 'waiting_for_approval')->count(),

                // Book health
                'active_loans' => Loan::whereIn('status', ['disbursed'])->count(),
                'total_book_value' => (float) Loan::whereIn('status', ['disbursed'])->sum('remaining_balance'),
                'overdue_count' => RepaymentSchedule::where('status', 'pending')
                    ->whereDate('due_date', '<', $now)->count(),
                'overdue_amount' => (float) RepaymentSchedule::where('status', 'pending')
                    ->whereDate('due_date', '<', $now)->sum('emi_amount'),

                // Collections today
                'collected_today' => (float) RepaymentSchedule::where('status', 'paid')
                    ->whereDate('paid_at', today())->sum('emi_amount'),
                'failed_today' => RepaymentSchedule::where('status', 'payment_failed')
                    ->whereDate('updated_at', today())->count(),

                // This month
                'disbursed_this_month' => (float) Loan::whereMonth('disbursed_date', $now->month)
                    ->whereYear('disbursed_date', $now->year)
                    ->sum('loan_amount'),
                'collected_this_month' => (float) RepaymentSchedule::where('status', 'paid')
                    ->whereMonth('paid_at', $now->month)
                    ->whereYear('paid_at', $now->year)
                    ->sum('emi_amount'),

                // Portfolio at Risk
                'par_percentage' => $this->parPercentage(),

                // Period status
                'current_period' => FinancialPeriod::current()?->period ?? $now->format('Y-m'),
                'period_status' => FinancialPeriod::current()?->status ?? 'open',

                // Last Nu-Pay import
                'last_import' => DB::table('import_batches')
                    ->where('source', 'nupay')
                    ->orderBy('created_at', 'desc')
                    ->select('import_ref', 'status', 'row_count', 'created_at')
                    ->first(),
            ];
        }));
    }

    // ── CLO decision (advisory recommendation for a loan application) ─────────

    public function cloDecision(int $id)
    {
        $application = LoanApplication::findOrFail($id);
        $decision = CloDecision::latestFor($application->id)->first();

        if (! $decision) {
            return response()->json(['message' => 'No CLO decision recorded for this application.'], 404);
        }

        return response()->json([
            'application_id' => $application->id,
            'decision' => $decision->decision,
            'risk_score' => $decision->risk_score,
            'compliance_status' => $decision->compliance_status,
            'fraud_flags' => $decision->fraud_flags,
            'policy_references' => $decision->policy_references,
            'reason' => $decision->reason,
            'required_actions' => $decision->required_actions,
            'audit_required' => $decision->audit_required,
            'evaluated_at' => $decision->evaluated_at,
        ]);
    }

    // ── Pending disbursements (approve from phone) ────────────────────────────

    public function disbursements()
    {
        $items = LoanDisbursement::with(['loan', 'loan.user', 'loan.loanApplication'])
            ->where('status', 'waiting_for_approval')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'client_name' => $d->loan?->user?->name,
                'customer_code' => $d->loan?->user?->customer?->customer_code,
                'loan_id' => $d->loan_id,
                'amount' => (float) $d->disbursed_amount,
                'loan_type' => $d->loan?->loan_type,
                'created_at' => $d->created_at,
                'reference' => $d->payment_reference,
                'affordability_ok' => ($d->loan?->loanApplication?->affordability_instalment_requested ?? 0)
                                        <= ($d->loan?->loanApplication?->affordability_max_instalment ?? PHP_INT_MAX),
            ]);

        return response()->json(['data' => $items, 'count' => $items->count()]);
    }

    public function approveDisbursement(Request $request, int $id)
    {
        try {
            app(\App\Services\DisbursementService::class)->approveAndPost($id, $request->user()->id);

            return response()->json(['message' => 'Disbursement approved and posted to GL.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rejectDisbursement(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $disb = LoanDisbursement::findOrFail($id);
        if ($disb->status !== 'waiting_for_approval') {
            return response()->json(['message' => 'Cannot reject in current state.'], 422);
        }

        $disb->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Disbursement rejected.']);
    }

    // ── Overdue accounts ──────────────────────────────────────────────────────

    public function overdue()
    {
        return response()->json(Cache::remember('admin_api_overdue', 60, fn () => $this->overdueData()));
    }

    private function overdueData(): array
    {
        $overdue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', now())
            ->select(
                'l.id as loan_id',
                'u.name as client_name',
                'u.phone',
                'c.customer_code',
                'l.remaining_balance',
                DB::raw('SUM(rs.emi_amount) as arrears_total'),
                DB::raw('DATEDIFF(CURDATE(), MIN(rs.due_date)) as days_overdue'),
                DB::raw('COUNT(rs.id) as missed_instalments'),
                DB::raw('CASE WHEN DATEDIFF(CURDATE(),MIN(rs.due_date))>=90 THEN 3
                              WHEN DATEDIFF(CURDATE(),MIN(rs.due_date))>=30 THEN 2
                              ELSE 1 END as ifrs9_stage')
            )
            ->groupBy('l.id', 'u.name', 'u.phone', 'c.customer_code', 'l.remaining_balance')
            ->orderByDesc('days_overdue')
            ->get()
            ->map(fn ($r) => [
                'loan_id' => $r->loan_id,
                'client_name' => $r->client_name,
                'phone' => $r->phone,
                'customer_code' => $r->customer_code,
                'remaining_balance' => (float) $r->remaining_balance,
                'arrears_total' => (float) $r->arrears_total,
                'days_overdue' => (int) $r->days_overdue,
                'missed_instalments' => (int) $r->missed_instalments,
                'ifrs9_stage' => (int) $r->ifrs9_stage,
                'dpd_bucket' => $r->days_overdue >= 90 ? '90+ DPD'
                    : ($r->days_overdue >= 60 ? '60-89 DPD'
                    : ($r->days_overdue >= 30 ? '30-59 DPD' : '1-29 DPD')),
                'whatsapp_url' => $r->phone ? 'https://wa.me/'.preg_replace('/\D/', '', $r->phone).'?text='.urlencode("Hi {$r->client_name}, this is Keystone Capital Partners. Your loan repayment is {$r->days_overdue} days overdue. Please contact us urgently on 27721853349.") : null,
            ]);

        $summary = [
            'stage1' => $overdue->where('ifrs9_stage', 1)->count(),
            'stage2' => $overdue->where('ifrs9_stage', 2)->count(),
            'stage3' => $overdue->where('ifrs9_stage', 3)->count(),
            'total_arrears' => $overdue->sum('arrears_total'),
        ];

        return ['data' => $overdue, 'summary' => $summary];
    }

    // ── Collections today ─────────────────────────────────────────────────────

    public function collections()
    {
        return response()->json(Cache::remember('admin_api_collections_'.today()->toDateString(), 60, fn () => $this->collectionsData()));
    }

    private function collectionsData(): array
    {
        $today = today()->toDateString();

        $paid = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('rs.status', 'paid')
            ->whereDate('rs.paid_at', $today)
            ->select('u.name', 'rs.emi_amount', 'rs.paid_at', 'rs.interest_amount', 'rs.fee_amount')
            ->orderByDesc('rs.paid_at')
            ->get();

        $failed = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('rs.status', 'payment_failed')
            ->whereDate('rs.updated_at', $today)
            ->select('u.name', 'u.phone', 'rs.emi_amount', 'rs.due_date')
            ->get();

        $nuPayBatch = DB::table('import_batches')
            ->where('source', 'nupay')
            ->whereDate('created_at', $today)
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'date' => $today,
            'total_collected' => (float) $paid->sum('emi_amount'),
            'payments_count' => $paid->count(),
            'failed_count' => $failed->count(),
            'failed_amount' => (float) $failed->sum('emi_amount'),
            'nupay_batch' => $nuPayBatch ? [
                'ref' => $nuPayBatch->import_ref,
                'status' => $nuPayBatch->status,
                'row_count' => $nuPayBatch->row_count,
                'imported_at' => $nuPayBatch->created_at,
            ] : null,
            'paid' => $paid,
            'failed' => $failed,
        ];
    }

    // ── Alerts ────────────────────────────────────────────────────────────────

    public function alerts()
    {
        return response()->json(Cache::remember('admin_api_alerts', 60, fn () => $this->alertsData()));
    }

    private function alertsData(): array
    {
        $alerts = [];

        // Stage 3 accounts (90+ DPD)
        $stage3 = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereRaw('DATEDIFF(CURDATE(), rs.due_date) >= 90')
            ->select('l.id as loan_id', 'u.name', DB::raw('SUM(rs.emi_amount) as amount'), DB::raw('DATEDIFF(CURDATE(), MIN(rs.due_date)) as dpd'))
            ->groupBy('l.id', 'u.name')
            ->get();

        foreach ($stage3 as $a) {
            $alerts[] = ['type' => 'stage3', 'severity' => 'critical', 'title' => '90+ DPD — Non-Performing', 'body' => "{$a->name} · R".number_format($a->amount, 2)." · {$a->dpd} days", 'loan_id' => $a->loan_id];
        }

        // Unbalanced GL batches
        $unbalanced = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->select('gb.id', 'gb.reference', DB::raw('ROUND(ABS(SUM(ge.debit) - SUM(ge.credit)),2) as variance'))
            ->groupBy('gb.id', 'gb.reference')
            ->having('variance', '>', 0.01)
            ->count();

        if ($unbalanced > 0) {
            $alerts[] = ['type' => 'gl_error', 'severity' => 'critical', 'title' => 'Unbalanced GL Batches', 'body' => "{$unbalanced} GL batch(es) are unbalanced — check GL reconciliation."];
        }

        // Pending disbursements > 24 hours old
        $oldPending = LoanDisbursement::where('status', 'waiting_for_approval')
            ->where('created_at', '<', now()->subHours(24))->count();

        if ($oldPending > 0) {
            $alerts[] = ['type' => 'disbursement', 'severity' => 'warning', 'title' => 'Disbursements Awaiting Approval', 'body' => "{$oldPending} disbursement(s) waiting more than 24 hours."];
        }

        // Payments due tomorrow
        $dueTomorrow = RepaymentSchedule::where('status', 'pending')
            ->whereDate('due_date', now()->addDay())->count();

        if ($dueTomorrow > 0) {
            $alerts[] = ['type' => 'collection', 'severity' => 'info', 'title' => 'Payments Due Tomorrow', 'body' => "{$dueTomorrow} instalment(s) due tomorrow. Ensure debit orders are submitted."];
        }

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $alerts[] = ['type' => 'system', 'severity' => 'warning', 'title' => 'Failed Queue Jobs', 'body' => "{$failedJobs} failed job(s) in the queue. Review system logs."];
        }

        return ['data' => $alerts, 'count' => count($alerts)];
    }

    // ── Portfolio summary ──────────────────────────────────────────────────────

    public function portfolio()
    {
        return response()->json(Cache::remember('admin_api_portfolio', 60, fn () => $this->portfolioData()));
    }

    private function portfolioData(): array
    {
        return [
            'principal_deployed' => (float) Loan::whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])->sum('principal_amount'),
            'total_outstanding' => (float) Loan::whereIn('status', ['disbursed'])->sum('remaining_balance'),
            'par_percentage' => $this->parPercentage(),
            'collection_rate_month' => $this->collectionRateThisMonth(),
            'active_loans' => Loan::whereIn('status', ['disbursed'])->count(),
            'settled_this_month' => Loan::where('status', 'settled')->whereMonth('updated_at', now()->month)->count(),
            'new_disbursements_month' => (float) Loan::whereMonth('disbursed_date', now()->month)->whereYear('disbursed_date', now()->year)->sum('loan_amount'),
            'monthly_trend' => DB::table('repayment_schedules as rs')
                ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
                ->where('rs.status', 'paid')
                ->whereRaw('rs.paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
                ->select(DB::raw("DATE_FORMAT(rs.paid_at,'%Y-%m') as month"), DB::raw('SUM(rs.emi_amount) as collected'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parPercentage(): float
    {
        $total = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])->sum('rs.emi_amount');
        $overdue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', now())->sum('rs.emi_amount');

        return $total > 0 ? round(($overdue / $total) * 100, 2) : 0;
    }

    private function collectionRateThisMonth(): float
    {
        $due = DB::table('repayment_schedules')
            ->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year)
            ->sum('emi_amount');
        $collected = DB::table('repayment_schedules')
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('emi_amount');

        return $due > 0 ? round(($collected / $due) * 100, 2) : 0;
    }
}
