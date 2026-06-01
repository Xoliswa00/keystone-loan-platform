<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use App\Models\Customer;
use App\Models\RepaymentSchedule;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use Illuminate\Support\Facades\DB;

use App\Models\Loan;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    return view('admin.dashboard', [

        // Pending loan applications (system-wide or admin-scoped)
        'pendingLoansCount' => LoanApplication::where('status', 'pending')->count(),

        // Total customers
        'customerCount' => Customer::count(),

        // Today's pending repayments (correct definition)
        'todaysRepayments' => RepaymentSchedule::join('loans', 'repayment_schedules.loan_id', '=', 'loans.loan_application_id')
    ->where('repayment_schedules.status', 'pending')
    //->where('loans.user_id', auth()->id())
    ->sum('repayment_schedules.emi_amount'),
            'overdueLoansCount' => LoanApplication::where('status','approved')->count(),
'recentApplications' => LoanApplication::latest()->take(5)->get(),

        // Loans waiting for approval
        'totalLoansDisbursed' => LoanDisbursement::where('status', 'waiting_for_approval')->count(),
    ]);
    }

    public function Loans()
    {

          $pendingApplications = LoanApplication::with('user')
            ->where('status', 'pending')
           // ->latest()
            ->paginate(15);

        return view('admin.loan_applications.index', compact('pendingApplications'));
    

    }


    






    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdminRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
  public function show($id)
{
    $application = LoanApplication::findOrFail($id);

    $previousLoans = LoanApplication::where('user_id', $application->user_id)
        ->where('id', '!=', $application->id)
        ->get();

    return view('admin.loan_applications.show', compact('application', 'previousLoans'));
}


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdminRequest $request, Admin $admin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Admin $admin)
    {
        //
    }

    //loan disbursement waiting for approval

    public function Disbursement()
    {
        $disbursements = LoanDisbursement::where('Status', 'waiting_for_approval')->get();
        return view('admin.loans.payments', compact('disbursements'));
      
    
    }
    public function summary(Request $request)
    {
        $from = $request->from_date ?? now()->startOfMonth();
        $to   = $request->to_date ?? now();
    
        $accounts = DB::table('glbatches as jl')
            ->join('glentries as je', 'jl.id', '=', 'je.batch_id')
            ->join('gl_accounts as ga', 'jl.gl_account_id', '=', 'ga.id')
            ->whereBetween('je.entry_date', [$from, $to])
            ->select([
                'ga.id',
                'ga.code',
                'ga.name',
                'ga.type',
                'ga.normal_balance',
                DB::raw('SUM(jl.debit) as total_debit'),
                DB::raw('SUM(jl.credit) as total_credit'),
            ])
            ->groupBy(
                'ga.id',
                'ga.code',
                'ga.name',
                'ga.type',
                'ga.normal_balance'
            )
            ->orderBy('ga.code')
            ->get();
    
        return view('admin.reports.gl_summary', compact('accounts', 'from', 'to'));
    }
    public function portfolio()
{
    $now = now();
    $currentMonth = $now->month;
    $currentYear = $now->year;
    $lastMonthDate = $now->copy()->subMonth();
    $lastMonth = $lastMonthDate->month;
    $lastMonthYear = $lastMonthDate->year;

    /* ============================================================
       1️⃣ CAPITAL DEPLOYED (Principal at Risk)
    ============================================================ */

    $principalDeployed = Loan::where('status','!=','Settled')
        ->sum('loan_amount');


    /* ============================================================
       2️⃣ TOTAL OUTSTANDING (Remaining Contractual Cashflow)
    ============================================================ */

    $totalOutstanding = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->whereIn('rs.status',[ 'pending','Failed'])
        ->sum('rs.emi_amount');


    /* ============================================================
       3️⃣ OVERDUE EXPOSURE (Risk Layer)
    ============================================================ */

    $overdueAmount = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.id', '=', 'rs.loan_id')
        ->whereIn('rs.status',[ 'pending','Failed'])
        ->whereDate('rs.due_date', '<', $now)
        ->sum('rs.emi_amount');

    $parPercentage = ($overdueAmount / $totalOutstanding) * 100;


    /* ============================================================
       4️⃣ ESTIMATED COLLECTIBLE (Provision Adjusted)
    ============================================================ */

    $provisionRate = 0.2; // 50% provisioning assumption (adjustable)

    $estimatedCollectible = $totalOutstanding - ($overdueAmount );


    /* ============================================================
       5️⃣ CASH COLLECTION (Real Revenue)
    ============================================================ */

    $currentMonthCollections = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status', 'paid')
        ->whereMonth('rs.due_date', $currentMonth)
        ->whereYear('rs.due_date', $currentYear)
        ->sum('rs.emi_amount');

    $lastMonthCollections = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status', 'paid')
        ->whereMonth('rs.due_date', $lastMonth)
        ->whereYear('rs.due_date', $lastMonthYear)
        ->sum('rs.emi_amount');

    $revenueGrowth = $lastMonthCollections > 0
        ? (($currentMonthCollections - $lastMonthCollections) / $lastMonthCollections) * 100
        : 0;
        
        $monthlyRevenue = DB::table('repayment_schedules as rs')
    ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
    ->where('rs.status', 'paid')
    ->select(
        DB::raw("DATE_FORMAT(rs.due_date, '%Y-%m') as month"),
        DB::raw("SUM(rs.emi_amount) as total_collected")
    )
    ->groupBy('month')
    ->orderBy('month')
    ->get();



    /* ============================================================
       6️⃣ COLLECTION EFFICIENCY (Due vs Collected This Month)
    ============================================================ */

    $dueThisMonth = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->whereIn('rs.status',[ 'pending','Failed'])
        ->whereMonth('rs.due_date', $currentMonth)
        ->whereYear('rs.due_date', $currentYear)
        ->sum('rs.emi_amount');

    $collectionRate = $dueThisMonth > 0
        ? ($currentMonthCollections / $dueThisMonth) * 100
        : 0;


    /* ============================================================
       7️⃣ AVERAGE LOAN SIZE (Active Book)
    ============================================================ */

    $averageLoan = Loan::whereNotIn('status', ['pending'])

        ->avg('loan_amount');


    /* ============================================================
       8️⃣ PORTFOLIO GROWTH (Outstanding MoM)
    ============================================================ */

    $currentBook = $totalOutstanding;

    $lastMonthBook = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->whereIn('rs.status',[ 'pending','Failed'])
        ->whereDate('rs.due_date', '<=', $lastMonthDate->endOfMonth())
        ->sum('rs.emi_amount');

    $monthlyGrowth = $lastMonthBook > 0
        ? (($currentBook - $lastMonthBook) / $lastMonthBook) * 100
        : 0;


    /* ============================================================
       9️⃣ STATUS BREAKDOWN (Operational Visibility)
    ============================================================ */

    $statusBreakdown = Loan::select(
            'status',
            DB::raw('COUNT(*) as loan_count'),
            DB::raw('SUM(loan_amount) as amount')
        )
            ->whereNotIn('status', ['pending'])

        ->groupBy('status')
        ->get();


    /* ============================================================
       🔟 TOP 10 RISK EXPOSURES
    ============================================================ */

    $topOverdue = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->join('customers as c', 'c.user_id', '=', 'l.user_id')
        ->whereIn('rs.status',[ 'pending','Failed'])
            ->whereNotIn('l.status', ['pending'])

        ->whereDate('rs.due_date', '<', now())
        ->select(
            'l.id',
            'c.customer_code as customer_name',
            DB::raw('SUM(rs.emi_amount) as outstanding'),
            DB::raw('DATEDIFF(NOW(), MIN(rs.due_date)) as days_late')
        )
        ->groupBy('l.id', 'c.customer_code')
        ->orderByDesc('outstanding')
        ->limit(10)
        ->get();
        
        
        
        
        // Monthly Loan Activity
$monthlyActivity = DB::table('loans')
    ->whereNotIn('status', ['pending'])
    ->select(
        DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
        DB::raw("COUNT(*) as num_loans"),
        DB::raw("SUM(loan_amount) as total_disbursed")
        
    )
    ->groupBy('month')
    ->orderBy('month', 'asc')
    ->get();



    /* ============================================================
       RETURN VIEW
    ============================================================ */

    return view('admin.reports.portfolio', compact(
        'principalDeployed',
        'totalOutstanding',
        'overdueAmount',
        'parPercentage',
        'estimatedCollectible',
        'currentMonthCollections',
        'revenueGrowth',
        'collectionRate',
        'averageLoan',
        'monthlyGrowth',
        'statusBreakdown',
        'topOverdue',
        'monthlyActivity',
        'monthlyRevenue'
    ));
}

    public function profitability()
{
    // ===============================
    // 1. Total Principal Disbursed
    // ===============================
    $totalPrincipal = Loan::sum('loan_amount');
        


    // ===============================
    // 2. Expected Revenue (Fees + Interest)
    // ===============================
    $expectedRevenue = DB::table('loan_fees as lf')
        ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
        ->whereNotIn('l.status', ['Settled','pending'])
        ->sum(DB::raw("
            COALESCE(lf.interest_amount,0) +
            COALESCE(lf.initiation_fee,0) +
            COALESCE(lf.service_fee,0)
        "));
        
    // ===============================
    // 2.  Collected  Revenue (Fees + Interest)
    // ===============================
    $Revenue = DB::table('loan_fees as lf')
        ->join('loans as l', 'l.loan_application_id', '=', 'lf.loan_application_id')
        ->whereIn('l.status', ['Settled'])
        ->sum(DB::raw("
            COALESCE(lf.interest_amount,0) +
            COALESCE(lf.initiation_fee,0) +
            COALESCE(lf.service_fee,0)
        "));


    // ===============================
    // 3.  Total Expected Cash Inflow - total money we should be making regarless of defaults 
    // ===============================
    $TotalCashFlow = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status','<>','rejected')
        ->sum('rs.emi_amount');



    // ===============================
    // 3. Collected Revenue
    // ===============================
    $collectedRevenue = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status', 'paid')
        ->sum('rs.emi_amount');


    // ===============================
    // 4. Outstanding (Revenue Still Deployed)
    // ===============================
    $totalOutstanding = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->whereIn('rs.status', ['pending','Failed'])
        ->sum('rs.emi_amount');


    // ===============================
    // 5. Net Revenue (Collected minus expected loss)
    // ===============================
    $overdueAmount = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->whereIn('rs.status', ['pending','Failed'])
        ->whereDate('rs.due_date','<',now())
        ->sum('rs.emi_amount');
        
        
            // ===============================
    // 5. Faild pyaments (  loss)
    // ===============================
    $FailedPayments = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status', 'Failed')
        ->sum('rs.emi_amount');
        
        
                
            // ===============================
    // 5. Faild pyaments (  loss) Capital
    // ===============================
    $FailedCapital = DB::table('loans as l')
            ->whereIn('l.status', ['Payment Failed'])

        ->sum('l.loan_amount');
        
            // ===============================
    // 4. Outstanding (Capital Still Deployed)
    // ===============================
    $CapitalOutstanding = DB::table('loans as l')
                ->whereNotIn('l.status', ['Settled','pending'])

        ->sum('l.loan_amount');

        
        
        
        
        


    $netRevenue = $collectedRevenue - $overdueAmount;


    // ===============================
    // 6. Portfolio Yield
    // ===============================
    $portfolioYield = $totalOutstanding > 0
        ? ($collectedRevenue / $totalOutstanding) * 100
        : 0;


    // ===============================
    // 7. Average Loan Margin
    // ===============================
    $avgMargin = $totalPrincipal > 0
        ? (($expectedRevenue - $overdueAmount) / $totalPrincipal) * 100
        : 0;


    // ===============================
    // 8. Monthly Revenue Trend
    // ===============================
    $monthlyRevenue = DB::table('repayment_schedules as rs')
        ->join('loans as l', 'l.loan_application_id', '=', 'rs.loan_id')
        ->where('rs.status','paid')
        ->select(
            DB::raw("DATE_FORMAT(rs.due_date,'%Y-%m') as month"),
            DB::raw("SUM(rs.emi_amount) as total_revenue")
        )
        ->groupBy('month')
        ->orderBy('month')
        ->get();


    return view('admin.reports.profitability', compact(
        'totalPrincipal',
        'expectedRevenue',
        'collectedRevenue',
        'netRevenue',
        'portfolioYield',
        'avgMargin',
        'monthlyRevenue',
        'totalOutstanding',
        'overdueAmount',
        'Revenue',
        'TotalCashFlow',
        'FailedPayments',
        'FailedCapital',
        'CapitalOutstanding'
    ));
}


public function scorecard()
{
    $summary = DB::table('reviewer_scorecard')
        ->orderByDesc('risky_exposure')
        ->get();

    $totals = [
        'total_outstanding' => $summary->sum('outstanding'),
        'total_risky'       => $summary->sum('risky_exposure'),
        'avg_default_rate'  => $summary->avg('default_rate'),
    ];

    // ORGANISATION monthly trend
    $orgTrend = DB::table('reviewer_monthly_trends')
        ->selectRaw("
            review_month,
            AVG(approval_rate) as approval_rate,
            AVG(default_rate) as default_rate
        ")
        ->groupBy('review_month')
        ->orderBy('review_month')
        ->get();

    return view('admin.reports.index', compact(
        'summary',
        'totals',
        'orgTrend'
    ));
}


 public function reviewer($id)
{
    /* ===== Reviewer Summary ===== */
    $reviewer = DB::table('reviewer_scorecard')
        ->where('id', $id)
        ->first();

    /* ===== Monthly trends ===== */
    $trends = DB::table('reviewer_monthly_trends')
        ->where('id', $id)
        ->orderBy('review_month')
        ->get();

    /* ===== Detailed loans ===== */

        
        
        $loans = DB::table('loan_applications as a')
    ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
            ->leftJoin('customers as c', 'c.user_id', '=', 'a.user_id')

    ->where('a.reviewer_id', $id)
    ->select(
        'a.id as application_id',
        'a.status',
        'l.status as loan_status',
        'l.remaining_balance',
        'a.created_at'
    )
    ->orderByDesc('a.created_at')
    ->get()
    ->groupBy('loan_status');   // <-- KEY LINE
        
        
        
        

    /* ===== Defaults linked to reviewer ===== */
    $defaults = DB::table('loan_applications as a')
        ->join('loans as l', 'l.loan_application_id', '=', 'a.id')
        ->join('users as u','u.id','=','l.user_id')
         ->Join('customers as c', 'c.user_id', '=', 'u.id')

        ->where('a.reviewer_id', $id)
        ->where('l.status', 'Payment Failed')
        ->select(
            'l.id',
            'l.remaining_balance',
            'l.updated_at','u.*','c.*','c.id as cust','reason'
        )
        ->get();

    return view('admin.reports.reviewer', compact(
        'reviewer',
        'trends',
        'loans',
        'defaults'
    ));
}

    public function alerts()
    {
        $alerts = DB::table('reviewer_alerts')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.reports.alerts', compact('alerts'));
    }



    

}
