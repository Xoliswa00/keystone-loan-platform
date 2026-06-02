<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\RepaymentSchedule;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\Loan;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Admin Dashboard
    // ──────────────────────────────────────────────────────────────────────────

    public function index()
    {
        // TODAY's repayments due — uses correct join direction
        $todaysRepayments = RepaymentSchedule::where('status', 'pending')
            ->whereDate('due_date', today())
            ->sum('emi_amount');

        return view('admin.dashboard', [
            'pendingLoansCount'   => LoanApplication::where('status', 'pending')->count(),
            'customerCount'       => Customer::count(),
            'todaysRepayments'    => $todaysRepayments,
            'overdueLoansCount'   => RepaymentSchedule::where('status', 'pending')
                                        ->whereDate('due_date', '<', today())->count(),
            'recentApplications'  => LoanApplication::with('user')->latest()->take(5)->get(),
            'totalLoansDisbursed' => LoanDisbursement::where('status', 'waiting_for_approval')->count(),
        ]);
    }

    public function Loans()
    {
        $pendingApplications = LoanApplication::with('user', 'product', 'loanfee')
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.loan_applications.index', compact('pendingApplications'));
    }

    public function show($id)
    {
        $application   = LoanApplication::with('user', 'user.customerProfile', 'loanfee', 'product', 'repaymentSchedules')
            ->findOrFail($id);
        $previousLoans = LoanApplication::where('user_id', $application->user_id)
            ->where('id', '<>', $application->id)->get();

        return view('admin.loan_applications.show', compact('application', 'previousLoans'));
    }

    public function Disbursement()
    {
        $disbursements = LoanDisbursement::with('loan', 'loan.user')
            ->where('status', 'waiting_for_approval')
            ->get();
        return view('admin.loans.payments', compact('disbursements'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GL Summary (Trial Balance)
    // Fixed: correct joins through glentries, not glbatches
    // ──────────────────────────────────────────────────────────────────────────

    public function summary(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        // Correct join: glbatches → glentries → gl_accounts
        $accounts = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->join('gl_accounts as ga', 'ge.account_id', '=', 'ga.id')
            ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
            ->whereBetween('gb.posted_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select([
                'coa.account_code',
                'coa.account_category',
                'coa.account_type',
                DB::raw('SUM(ge.debit)  as total_debit'),
                DB::raw('SUM(ge.credit) as total_credit'),
                DB::raw('SUM(ge.debit) - SUM(ge.credit) as net_balance'),
            ])
            ->groupBy('coa.account_code', 'coa.account_category', 'coa.account_type')
            ->orderBy('coa.account_code')
            ->get();

        $totalDebits  = $accounts->sum('total_debit');
        $totalCredits = $accounts->sum('total_credit');
        $balanced     = round($totalDebits, 2) === round($totalCredits, 2);

        return view('admin.reports.gl_summary', compact('accounts', 'from', 'to', 'totalDebits', 'totalCredits', 'balanced'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Portfolio Report
    // ──────────────────────────────────────────────────────────────────────────

    public function portfolio()
    {
        $now           = now();
        $lastMonthDate = $now->copy()->subMonth();

        // All schedule joins use loan_application_id = rs.loan_id (consistent)

        $principalDeployed = Loan::whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->sum('principal_amount');

        $totalOutstanding = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->sum('rs.emi_amount');

        // Overdue — consistent join direction
        $overdueAmount = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', $now)
            ->sum('rs.emi_amount');

        // PAR (Portfolio At Risk)
        $parPercentage = $totalOutstanding > 0
            ? round(($overdueAmount / $totalOutstanding) * 100, 2)
            : 0;

        // IFRS 9 — provision from bad_debt_provisions (latest per loan)
        $totalProvision = DB::table('bad_debt_provisions')
            ->whereIn('loan_id', function ($q) {
                $q->select('id')->from('loans')
                  ->whereNotIn('status', ['settled', 'written_off']);
            })
            ->select('loan_id', DB::raw('MAX(provision_amount) as latest_provision'))
            ->groupBy('loan_id')
            ->get()
            ->sum('latest_provision');

        $estimatedCollectible = $totalOutstanding - $totalProvision;

        // Collections
        $currentMonthCollections = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->whereMonth('rs.paid_at', $now->month)
            ->whereYear('rs.paid_at', $now->year)
            ->sum('rs.emi_amount');

        $lastMonthCollections = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->whereMonth('rs.paid_at', $lastMonthDate->month)
            ->whereYear('rs.paid_at', $lastMonthDate->year)
            ->sum('rs.emi_amount');

        $revenueGrowth = $lastMonthCollections > 0
            ? round((($currentMonthCollections - $lastMonthCollections) / $lastMonthCollections) * 100, 2)
            : 0;

        // Collection efficiency — due vs collected THIS month
        $dueThisMonth = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereMonth('rs.due_date', $now->month)
            ->whereYear('rs.due_date', $now->year)
            ->sum('rs.emi_amount');

        $collectionRate = $dueThisMonth > 0
            ? round(($currentMonthCollections / $dueThisMonth) * 100, 2)
            : 0;

        $averageLoan = Loan::whereNotIn('status', ['pending', 'rejected'])->avg('loan_amount');

        // Portfolio growth — disbursements MoM (not outstanding MoM, which would
        // show negative growth when clients repay — which is healthy)
        $newDisbursementsThisMonth = Loan::whereMonth('disbursed_date', $now->month)
            ->whereYear('disbursed_date', $now->year)
            ->sum('loan_amount');

        $newDisbursementsLastMonth = Loan::whereMonth('disbursed_date', $lastMonthDate->month)
            ->whereYear('disbursed_date', $lastMonthDate->year)
            ->sum('loan_amount');

        $monthlyGrowth = $newDisbursementsLastMonth > 0
            ? round((($newDisbursementsThisMonth - $newDisbursementsLastMonth) / $newDisbursementsLastMonth) * 100, 2)
            : 0;

        $statusBreakdown = Loan::select('status', DB::raw('COUNT(*) as loan_count'), DB::raw('SUM(loan_amount) as amount'))
            ->groupBy('status')
            ->get();

        // Top 10 overdue — consistent join
        $topOverdue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', $now)
            ->select(
                'l.id',
                'u.name as client_name',
                'c.customer_code',
                DB::raw('SUM(rs.emi_amount) as outstanding'),
                DB::raw('DATEDIFF(CURDATE(), MIN(rs.due_date)) as days_late')
            )
            ->groupBy('l.id', 'u.name', 'c.customer_code')
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get();

        $monthlyRevenue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->select(DB::raw("DATE_FORMAT(rs.paid_at, '%Y-%m') as month"), DB::raw('SUM(rs.emi_amount) as total_collected'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyActivity = DB::table('loans')
            ->whereNotIn('status', ['pending', 'rejected'])
            ->select(DB::raw("DATE_FORMAT(disbursed_date, '%Y-%m') as month"), DB::raw('COUNT(*) as num_loans'), DB::raw('SUM(loan_amount) as total_disbursed'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // IFRS 9 staging breakdown
        $ifrs9Staging = DB::table('bad_debt_provisions as p')
            ->join(DB::raw('(SELECT loan_id, MAX(provision_date) AS latest FROM bad_debt_provisions GROUP BY loan_id) latest'), function ($j) {
                $j->on('p.loan_id', '=', 'latest.loan_id')->on('p.provision_date', '=', 'latest.latest');
            })
            ->select('p.ifrs9_stage', DB::raw('COUNT(*) as count'), DB::raw('SUM(p.outstanding_balance) as outstanding'), DB::raw('SUM(p.provision_amount) as provision'))
            ->groupBy('p.ifrs9_stage')
            ->get();

        return view('admin.reports.portfolio', compact(
            'principalDeployed', 'totalOutstanding', 'overdueAmount',
            'parPercentage', 'estimatedCollectible', 'totalProvision',
            'currentMonthCollections', 'revenueGrowth', 'collectionRate',
            'averageLoan', 'monthlyGrowth', 'newDisbursementsThisMonth',
            'statusBreakdown', 'topOverdue', 'monthlyRevenue', 'monthlyActivity',
            'ifrs9Staging'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Profitability Report (P&L Summary)
    // ──────────────────────────────────────────────────────────────────────────

    public function profitability(Request $request)
    {
        $from = $request->from_date ?? now()->startOfYear()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $totalPrincipalDisbursed = Loan::whereNotNull('disbursed_date')->sum('principal_amount');

        // ── Income: split by type (for IFRS 9 / NCR disclosure) ───────────────
        // Only include income from loans that have been disbursed (not just approved)
        $interestIncome = DB::table('loan_fees as lf')
            ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
            ->whereNotNull('l.disbursed_date')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->sum('lf.interest_amount');

        $initiationFeeIncome = DB::table('loan_fees as lf')
            ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
            ->whereNotNull('l.disbursed_date')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->sum('lf.initiation_fee');

        $serviceFeeIncome = DB::table('loan_fees as lf')
            ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
            ->whereNotNull('l.disbursed_date')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->sum('lf.service_fee');

        // VAT on fees (15% of initiation + service fees)
        $vatOnFees = round(($initiationFeeIncome + $serviceFeeIncome) * 0.15, 2);

        $totalFeeIncome   = $initiationFeeIncome + $serviceFeeIncome;
        $totalGrossIncome = $interestIncome + $totalFeeIncome;

        // ── Collected vs expected ──────────────────────────────────────────────
        $collectedRevenue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->whereBetween('rs.paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('rs.interest_amount');  // only interest + fee portion, not principal

        $collectedFees = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->whereBetween('rs.paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('rs.fee_amount');

        $totalCollected = $collectedRevenue + $collectedFees;

        // ── Deferred income remaining (multi-month loans) ─────────────────────
        $totalDeferredInterest = Loan::whereIn('status', ['disbursed'])->sum('deferred_interest');
        $totalDeferredFees     = Loan::whereIn('status', ['disbursed'])->sum('deferred_fees');

        // ── Credit loss expense (IFRS 9 provisions) ───────────────────────────
        $creditLossExpense = DB::table('bad_debt_provisions')
            ->whereBetween('provision_date', [$from, $to])
            ->sum('provision_movement'); // net movement (can be negative = recovery)

        $writtenOff = Loan::where('status', 'written_off')
            ->whereBetween('written_off_date', [$from, $to])
            ->sum('write_off_amount');

        // ── Net revenue (correct definition) ──────────────────────────────────
        // Net revenue = collected income - credit loss expense - NuPay bank charges
        $bankCharges   = DB::table('loan_repayments')
            ->whereBetween('payment_date', [$from, $to])
            ->sum('nupay_fee');

        $netRevenue = $totalCollected - $creditLossExpense - $bankCharges;

        // ── Outstanding exposure ───────────────────────────────────────────────
        $totalOutstanding  = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->sum('rs.emi_amount');

        $overdueAmount = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', now())
            ->sum('rs.emi_amount');

        $failedPayments = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'payment_failed')
            ->sum('rs.emi_amount');

        $capitalOutstanding = Loan::whereIn('status', ['disbursed'])->sum('remaining_balance');

        // ── Ratios ─────────────────────────────────────────────────────────────
        $portfolioYield = $totalOutstanding > 0
            ? round(($totalCollected / $totalOutstanding) * 100, 2) : 0;

        $avgMargin = $totalPrincipalDisbursed > 0
            ? round(($totalGrossIncome / $totalPrincipalDisbursed) * 100, 2) : 0;

        $costOfRisk = $totalPrincipalDisbursed > 0
            ? round(($creditLossExpense / $totalPrincipalDisbursed) * 100, 2) : 0;

        // ── Monthly revenue trend ──────────────────────────────────────────────
        $monthlyRevenue = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->where('rs.status', 'paid')
            ->select(
                DB::raw("DATE_FORMAT(rs.paid_at, '%Y-%m') as month"),
                DB::raw('SUM(rs.interest_amount) as interest_income'),
                DB::raw('SUM(rs.fee_amount) as fee_income'),
                DB::raw('SUM(rs.interest_amount + rs.fee_amount) as total_revenue'),
                DB::raw('COUNT(*) as payments_count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.reports.profitability', compact(
            'totalPrincipalDisbursed',
            'interestIncome', 'initiationFeeIncome', 'serviceFeeIncome',
            'totalFeeIncome', 'totalGrossIncome', 'vatOnFees',
            'collectedRevenue', 'collectedFees', 'totalCollected',
            'totalDeferredInterest', 'totalDeferredFees',
            'creditLossExpense', 'writtenOff', 'bankCharges',
            'netRevenue', 'portfolioYield', 'avgMargin', 'costOfRisk',
            'totalOutstanding', 'overdueAmount', 'failedPayments', 'capitalOutstanding',
            'monthlyRevenue', 'from', 'to'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Arrears Report (DPD buckets — IFRS 9 compliant)
    // ──────────────────────────────────────────────────────────────────────────

    public function arrears(Request $request)
    {
        $asAt = $request->as_at_date ?? now()->toDateString();

        $buckets = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereDate('rs.due_date', '<', $asAt)
            ->select(
                'l.id as loan_id',
                'u.name as client_name',
                'c.customer_code',
                'l.loan_amount',
                'l.remaining_balance',
                DB::raw('SUM(rs.emi_amount) as arrears_amount'),
                DB::raw('DATEDIFF(?, MIN(rs.due_date)) as days_past_due'),
                DB::raw('CASE
                    WHEN DATEDIFF(?, MIN(rs.due_date)) < 30  THEN "1-29 DPD"
                    WHEN DATEDIFF(?, MIN(rs.due_date)) < 60  THEN "30-59 DPD"
                    WHEN DATEDIFF(?, MIN(rs.due_date)) < 90  THEN "60-89 DPD"
                    WHEN DATEDIFF(?, MIN(rs.due_date)) < 180 THEN "90-179 DPD"
                    ELSE "180+ DPD"
                END as dpd_bucket')
            )
            ->addBinding([$asAt, $asAt, $asAt, $asAt, $asAt], 'select')
            ->groupBy('l.id', 'u.name', 'c.customer_code', 'l.loan_amount', 'l.remaining_balance')
            ->orderByDesc('days_past_due')
            ->get();

        $summary = $buckets->groupBy('dpd_bucket')->map(fn($g) => [
            'count'   => $g->count(),
            'total'   => $g->sum('arrears_amount'),
            'balance' => $g->sum('remaining_balance'),
        ]);

        return view('admin.reports.arrears', compact('buckets', 'summary', 'asAt'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Collections Report
    // ──────────────────────────────────────────────────────────────────────────

    public function collections(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $collections = DB::table('loan_repayments as lr')
            ->join('loans as l', 'l.id', '=', 'lr.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->whereBetween('lr.payment_date', [$from, $to])
            ->where('lr.status', 'paid')
            ->select(
                'lr.id', 'u.name as client_name', 'c.customer_code',
                'l.id as loan_id', 'lr.payment_amount', 'lr.principal_amount',
                'lr.interest_amount', 'lr.fee_amount', 'lr.nupay_fee',
                'lr.payment_date', 'lr.payment_method', 'lr.payment_reference',
                'lr.transaction_type', 'lr.gl_batch_reference'
            )
            ->orderBy('lr.payment_date', 'desc')
            ->paginate(50);

        $totals = DB::table('loan_repayments as lr')
            ->whereBetween('lr.payment_date', [$from, $to])
            ->where('lr.status', 'paid')
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(lr.payment_amount) as total_collected'),
                DB::raw('SUM(lr.principal_amount) as total_principal'),
                DB::raw('SUM(lr.interest_amount) as total_interest'),
                DB::raw('SUM(lr.fee_amount) as total_fees'),
                DB::raw('SUM(lr.nupay_fee) as total_nupay_fees')
            )
            ->first();

        return view('admin.reports.collections', compact('collections', 'totals', 'from', 'to'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Disbursements Report
    // ──────────────────────────────────────────────────────────────────────────

    public function disbursements(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $disbursements = DB::table('loan_disbursements as ld')
            ->join('loans as l', 'l.id', '=', 'ld.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->leftJoin('loan_fees as lf', 'lf.loan_application_id', '=', 'l.loan_application_id')
            ->whereBetween('ld.disbursement_date', [$from, $to])
            ->where('ld.status', 'released')
            ->select(
                'ld.id', 'u.name as client_name', 'c.customer_code',
                'l.id as loan_id', 'l.loan_type',
                'ld.disbursed_amount', 'lf.initiation_fee',
                'lf.service_fee', 'lf.interest_amount', 'lf.total_due',
                'ld.disbursement_date', 'l.loan_term_months'
            )
            ->orderBy('ld.disbursement_date', 'desc')
            ->paginate(50);

        $totals = DB::table('loan_disbursements as ld')
            ->whereBetween('ld.disbursement_date', [$from, $to])
            ->where('ld.status', 'released')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('SUM(ld.disbursed_amount) as total'))
            ->first();

        return view('admin.reports.disbursements', compact('disbursements', 'totals', 'from', 'to'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GL Reconciliation
    // ──────────────────────────────────────────────────────────────────────────

    public function glReconciliation(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $batches = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->whereBetween('gb.posted_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select(
                'gb.id', 'gb.reference', 'gb.source_type', 'gb.posted_at',
                DB::raw('SUM(ge.debit)  as total_debit'),
                DB::raw('SUM(ge.credit) as total_credit'),
                DB::raw('ABS(SUM(ge.debit) - SUM(ge.credit)) as variance')
            )
            ->groupBy('gb.id', 'gb.reference', 'gb.source_type', 'gb.posted_at')
            ->having('variance', '>', 0.01)  // show unbalanced batches only (for reconciliation)
            ->orderByDesc('gb.posted_at')
            ->get();

        $summary = DB::table('glbatches as gb')
            ->join('glentries as ge', 'gb.id', '=', 'ge.batch_id')
            ->whereBetween('gb.posted_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select(DB::raw('SUM(ge.debit) as total_debit, SUM(ge.credit) as total_credit'))
            ->first();

        return view('admin.reports.gl_reconciliation', compact('batches', 'summary', 'from', 'to'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reversal Audit
    // ──────────────────────────────────────────────────────────────────────────

    public function reversalAudit(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $reversals = DB::table('loan_repayments as lr')
            ->join('loans as l', 'l.id', '=', 'lr.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('lr.transaction_type', 'reversed')
            ->whereBetween('lr.payment_date', [$from, $to])
            ->select('lr.*', 'u.name as client_name', 'l.id as loan_id')
            ->orderByDesc('lr.payment_date')
            ->get();

        return view('admin.reports.reversals', compact('reversals', 'from', 'to'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scorecard — computed from real data (replaces missing reviewer_scorecard view)
    // ──────────────────────────────────────────────────────────────────────────

    public function scorecard()
    {
        $summary = DB::table('loan_applications as a')
            ->join('users as r', 'r.id', '=', 'a.reviewer_id')
            ->leftJoin('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->whereNotNull('a.reviewer_id')
            ->select(
                'a.reviewer_id as id',
                'r.name as reviewer_name',
                DB::raw('COUNT(a.id) as applications_reviewed'),
                DB::raw("SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as applications_approved"),
                DB::raw("SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as applications_rejected"),
                DB::raw("ROUND(SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) / COUNT(a.id) * 100, 1) as approval_rate"),
                DB::raw('SUM(COALESCE(l.loan_amount, 0)) as total_disbursed'),
                DB::raw("SUM(CASE WHEN l.status IN ('disbursed') THEN l.remaining_balance ELSE 0 END) as outstanding"),
                DB::raw("SUM(CASE WHEN l.status = 'written_off' THEN l.loan_amount ELSE 0 END) as risky_exposure"),
                DB::raw("ROUND(SUM(CASE WHEN l.status = 'written_off' THEN l.loan_amount ELSE 0 END) / NULLIF(SUM(l.loan_amount),0) * 100, 2) as default_rate")
            )
            ->groupBy('a.reviewer_id', 'r.name')
            ->orderByDesc('risky_exposure')
            ->get();

        $totals = [
            'total_outstanding' => $summary->sum('outstanding'),
            'total_risky'       => $summary->sum('risky_exposure'),
            'avg_default_rate'  => round($summary->avg('default_rate'), 2),
        ];

        $orgTrend = DB::table('loan_applications as a')
            ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->whereNotNull('a.reviewer_id')
            ->select(
                DB::raw("DATE_FORMAT(a.approval_date, '%Y-%m') as review_month"),
                DB::raw("ROUND(SUM(CASE WHEN a.status='approved' THEN 1 ELSE 0 END)/COUNT(a.id)*100,1) as approval_rate"),
                DB::raw("ROUND(SUM(CASE WHEN l.status='written_off' THEN 1 ELSE 0 END)/NULLIF(COUNT(l.id),0)*100,1) as default_rate")
            )
            ->groupBy('review_month')
            ->orderBy('review_month')
            ->get();

        return view('admin.reports.index', compact('summary', 'totals', 'orgTrend'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Per-reviewer detail
    // ──────────────────────────────────────────────────────────────────────────

    public function reviewer($id)
    {
        $reviewer = DB::table('users')->find($id);

        $trends = DB::table('loan_applications as a')
            ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->where('a.reviewer_id', $id)
            ->select(
                DB::raw("DATE_FORMAT(a.approval_date, '%Y-%m') as review_month"),
                DB::raw("ROUND(SUM(CASE WHEN a.status='approved' THEN 1 ELSE 0 END)/COUNT(a.id)*100,1) as approval_rate"),
                DB::raw("ROUND(SUM(CASE WHEN l.status='written_off' THEN 1 ELSE 0 END)/NULLIF(COUNT(l.id),0)*100,1) as default_rate")
            )
            ->groupBy('review_month')
            ->orderBy('review_month')
            ->get();

        $loans = DB::table('loan_applications as a')
            ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'a.user_id')
            ->where('a.reviewer_id', $id)
            ->select('a.id as application_id', 'a.status', 'l.status as loan_status', 'l.remaining_balance', 'a.created_at', 'c.customer_code')
            ->orderByDesc('a.created_at')
            ->get()
            ->groupBy('loan_status');

        $defaults = DB::table('loan_applications as a')
            ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'u.id')
            ->where('a.reviewer_id', $id)
            ->whereIn('l.status', ['written_off', 'payment_failed'])
            ->select('l.id', 'l.remaining_balance', 'l.updated_at', 'u.name', 'c.customer_code', 'a.reason')
            ->get();

        return view('admin.reports.reviewer', compact('reviewer', 'trends', 'loans', 'defaults'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Alerts — from real data (overdue, upcoming payments, etc.)
    // ──────────────────────────────────────────────────────────────────────────

    public function alerts()
    {
        $alerts = collect();

        // Loans 90+ DPD (Stage 3)
        $stage3 = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('rs.status', ['pending', 'payment_failed'])
            ->whereRaw('DATEDIFF(CURDATE(), rs.due_date) >= 90')
            ->select('l.id as loan_id', 'u.name as client', DB::raw('SUM(rs.emi_amount) as amount'), DB::raw('DATEDIFF(CURDATE(), MIN(rs.due_date)) as dpd'))
            ->groupBy('l.id', 'u.name')
            ->get()
            ->map(fn($r) => (object)['type' => 'Stage 3 — 90+ DPD', 'severity' => 'critical', 'loan_id' => $r->loan_id, 'client' => $r->client, 'amount' => $r->amount, 'dpd' => $r->dpd, 'created_at' => now()]);

        // Payments due tomorrow
        $dueTomorrow = DB::table('repayment_schedules as rs')
            ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('rs.status', 'pending')
            ->whereDate('rs.due_date', now()->addDay())
            ->select('l.id as loan_id', 'u.name as client', 'rs.emi_amount as amount', 'rs.due_date')
            ->get()
            ->map(fn($r) => (object)['type' => 'Payment Due Tomorrow', 'severity' => 'warning', 'loan_id' => $r->loan_id, 'client' => $r->client, 'amount' => $r->amount, 'dpd' => 0, 'created_at' => now()]);

        $alerts = $stage3->merge($dueTomorrow)->sortByDesc('severity');

        return view('admin.reports.alerts', compact('alerts'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // VAT 201 — SARS VAT return extract
    // ──────────────────────────────────────────────────────────────────────────

    public function vat201(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        // Output VAT — on initiation fees + service fees (VAT-able supplies)
        $outputVatOnFees = DB::table('loan_fees as lf')
            ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->select(
                DB::raw('SUM(lf.initiation_fee) as initiation_fee_incl'),
                DB::raw('ROUND(SUM(lf.initiation_fee) / 1.15, 2) as initiation_fee_excl'),
                DB::raw('ROUND(SUM(lf.initiation_fee) - (SUM(lf.initiation_fee) / 1.15), 2) as initiation_vat'),
                DB::raw('SUM(lf.service_fee) as service_fee_incl'),
                DB::raw('ROUND(SUM(lf.service_fee) / 1.15, 2) as service_fee_excl'),
                DB::raw('ROUND(SUM(lf.service_fee) - (SUM(lf.service_fee) / 1.15), 2) as service_vat')
            )
            ->first();

        // Interest income is VAT-exempt (financial services exemption)
        $interestIncome = DB::table('loan_fees as lf')
            ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
            ->whereBetween('l.disbursed_date', [$from, $to])
            ->sum('lf.interest_amount');

        // Input VAT — on NuPay collection fees and other business expenses
        $inputVatOnNupay = DB::table('loan_repayments')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('ROUND(SUM(nupay_fee) / 1.15 * 0.15, 2) as input_vat')
            ->value('input_vat') ?? 0;

        $totalOutputVat = round(($outputVatOnFees->initiation_vat ?? 0) + ($outputVatOnFees->service_vat ?? 0), 2);
        $totalInputVat  = $inputVatOnNupay;
        $netVatPayable  = round($totalOutputVat - $totalInputVat, 2);

        return view('admin.reports.vat201', compact(
            'outputVatOnFees', 'interestIncome',
            'totalOutputVat', 'totalInputVat', 'netVatPayable',
            'from', 'to'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Income Statement (IFRS-formatted P&L)
    // ──────────────────────────────────────────────────────────────────────────

    public function incomeStatement(Request $request)
    {
        $from = $request->from_date ?? now()->startOfYear()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $data = [
            // Revenue
            'interest_income'        => DB::table('repayment_schedules as rs')
                ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
                ->where('rs.status', 'paid')->whereBetween('rs.paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->sum('rs.interest_amount'),

            'fee_income_excl_vat'    => DB::table('repayment_schedules as rs')
                ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
                ->where('rs.status', 'paid')->whereBetween('rs.paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw('ROUND(SUM(rs.fee_amount) / 1.15, 2) as excl')->value('excl') ?? 0,

            'penalty_income'         => DB::table('loan_repayments')
                ->where('transaction_type', 'manual')->whereBetween('payment_date', [$from, $to])
                ->sum('fee_amount'),

            // Credit loss
            'credit_loss_expense'    => DB::table('bad_debt_provisions')
                ->whereBetween('provision_date', [$from, $to])
                ->sum('provision_movement'),

            // Operating expenses
            'bank_charges'           => DB::table('loan_repayments')
                ->whereBetween('payment_date', [$from, $to])->sum('nupay_fee'),

            // Deferred income (not yet in P&L — balance sheet item)
            'deferred_interest'      => Loan::where('status', 'disbursed')->sum('deferred_interest'),
            'deferred_fees'          => Loan::where('status', 'disbursed')->sum('deferred_fees'),

            'from' => $from,
            'to'   => $to,
        ];

        $data['gross_income']     = $data['interest_income'] + $data['fee_income_excl_vat'] + $data['penalty_income'];
        $data['total_expenses']   = $data['credit_loss_expense'] + $data['bank_charges'];
        $data['net_profit']       = $data['gross_income'] - $data['total_expenses'];

        return view('admin.reports.income_statement', $data);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Balance Sheet (summary)
    // ──────────────────────────────────────────────────────────────────────────

    public function balanceSheet(Request $request)
    {
        $asAt = $request->as_at_date ?? now()->toDateString();

        $loansReceivableGross = Loan::whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->sum('remaining_balance');

        $allowanceForCreditLoss = DB::table('bad_debt_provisions as p')
            ->join(DB::raw('(SELECT loan_id, MAX(provision_date) AS latest FROM bad_debt_provisions GROUP BY loan_id) latest'),
                fn($j) => $j->on('p.loan_id', '=', 'latest.loan_id')->on('p.provision_date', '=', 'latest.latest'))
            ->sum('p.provision_amount');

        $deferredInterest = Loan::where('status', 'disbursed')->sum('deferred_interest');
        $deferredFees     = Loan::where('status', 'disbursed')->sum('deferred_fees');

        $cashAtBank = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
            ->where('coa.account_code', '1100')
            ->value('ga.current_balance') ?? 0;

        $vatOutput = DB::table('gl_accounts as ga')
            ->join('chart_of_accounts as coa', 'ga.chart_id', '=', 'coa.id')
            ->where('coa.account_code', '2200')
            ->value('ga.current_balance') ?? 0;

        return view('admin.reports.balance_sheet', compact(
            'loansReceivableGross', 'allowanceForCreditLoss',
            'deferredInterest', 'deferredFees',
            'cashAtBank', 'vatOutput', 'asAt'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Write-off register
    // ──────────────────────────────────────────────────────────────────────────

    public function writeOffRegister(Request $request)
    {
        $from = $request->from_date ?? now()->startOfYear()->toDateString();
        $to   = $request->to_date   ?? now()->toDateString();

        $writeOffs = Loan::with(['user', 'user.customer'])
            ->where('status', 'written_off')
            ->whereBetween('written_off_date', [$from, $to])
            ->orderBy('written_off_date', 'desc')
            ->get();

        $totalWrittenOff = $writeOffs->sum('write_off_amount');

        return view('admin.reports.write_off_register', compact('writeOffs', 'totalWrittenOff', 'from', 'to'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NPL (Non-Performing Loan) Report — DPD staging + provision summary
    // ──────────────────────────────────────────────────────────────────────────

    public function nplReport(Request $request)
    {
        $asAt = $request->as_at_date ?? now()->toDateString();

        // All loans with any overdue schedules, staged by IFRS 9 DPD bucket
        $nplLoans = DB::table('loans as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'l.user_id')
            ->leftJoin(DB::raw('(
                SELECT loan_id, SUM(emi_amount) as arrears, COUNT(*) as missed_count,
                       MIN(due_date) as earliest_due,
                       DATEDIFF(CURDATE(), MIN(due_date)) as dpd
                FROM repayment_schedules
                WHERE status IN ("pending","payment_failed") AND due_date < CURDATE()
                GROUP BY loan_id
            ) rs'), 'rs.loan_id', '=', 'l.loan_application_id')
            ->leftJoin(DB::raw('(
                SELECT loan_id, SUM(provision_amount) as latest_provision
                FROM bad_debt_provisions p
                INNER JOIN (SELECT loan_id, MAX(provision_date) as md FROM bad_debt_provisions GROUP BY loan_id) lp
                ON p.loan_id = lp.loan_id AND p.provision_date = lp.md
                GROUP BY loan_id
            ) prov'), 'prov.loan_id', '=', 'l.id')
            ->whereNotNull('rs.dpd')
            ->where('rs.dpd', '>', 0)
            ->select(
                'l.id as loan_id', 'l.loan_application_id',
                'u.name as client_name', 'c.customer_code',
                'l.loan_amount', 'l.remaining_balance',
                'rs.arrears', 'rs.missed_count', 'rs.earliest_due', 'rs.dpd',
                DB::raw('CASE WHEN rs.dpd >= 90 THEN 3 WHEN rs.dpd >= 30 THEN 2 ELSE 1 END as ifrs9_stage'),
                DB::raw('CASE WHEN rs.dpd >= 90 THEN "90+ DPD" WHEN rs.dpd >= 60 THEN "60-89 DPD" WHEN rs.dpd >= 30 THEN "30-59 DPD" ELSE "1-29 DPD" END as dpd_bucket'),
                DB::raw('COALESCE(prov.latest_provision, 0) as provision_amount'),
                'l.status as loan_status'
            )
            ->orderByDesc('rs.dpd')
            ->paginate(50);

        // Stage summary
        $stageSummary = DB::table('loans as l')
            ->join(DB::raw('(
                SELECT loan_id, DATEDIFF(CURDATE(), MIN(due_date)) as dpd, SUM(emi_amount) as arrears
                FROM repayment_schedules WHERE status IN ("pending","payment_failed") AND due_date < CURDATE()
                GROUP BY loan_id
            ) rs'), 'rs.loan_id', '=', 'l.loan_application_id')
            ->leftJoin(DB::raw('(
                SELECT loan_id, SUM(provision_amount) as prov
                FROM bad_debt_provisions p
                INNER JOIN (SELECT loan_id, MAX(provision_date) as md FROM bad_debt_provisions GROUP BY loan_id) lp
                ON p.loan_id = lp.loan_id AND p.provision_date = lp.md GROUP BY loan_id
            ) prov'), 'prov.loan_id', '=', 'l.id')
            ->select(
                DB::raw('CASE WHEN rs.dpd >= 90 THEN "Stage 3 (90+ DPD)" WHEN rs.dpd >= 30 THEN "Stage 2 (30-89 DPD)" ELSE "Stage 1 (1-29 DPD)" END as stage'),
                DB::raw('COUNT(*) as loan_count'),
                DB::raw('SUM(l.remaining_balance) as total_outstanding'),
                DB::raw('SUM(rs.arrears) as total_arrears'),
                DB::raw('SUM(COALESCE(prov.prov,0)) as total_provision')
            )
            ->groupBy('stage')
            ->orderByDesc('stage')
            ->get();

        return view('admin.reports.npl', compact('nplLoans', 'stageSummary', 'asAt'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Audit log viewer
    // ──────────────────────────────────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $query = \App\Models\AuditLog::with('user')
            ->latest('created_at');

        if ($request->filled('model')) {
            $query->where('auditable_type', 'like', '%' . $request->model . '%');
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs  = $query->paginate(50);
        $users = \App\Models\User::orderBy('name')->get(['id', 'name']);

        return view('admin.reports.audit_log', compact('logs', 'users'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resource stubs
    // ──────────────────────────────────────────────────────────────────────────

    public function create(): void {}
    public function store(StoreAdminRequest $request): void {}
    public function edit(Admin $admin): void {}
    public function update(UpdateAdminRequest $request, Admin $admin): void {}
    public function destroy(Admin $admin): void {}
}
