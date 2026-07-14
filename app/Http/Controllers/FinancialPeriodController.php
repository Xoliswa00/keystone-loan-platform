<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Services\FinancialPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialPeriodController extends Controller
{
    protected FinancialPeriodService $service;

    public function __construct(FinancialPeriodService $service)
    {
        $this->service = $service;
    }

    // ── List all periods ──────────────────────────────────────────────────────

    public function index()
    {
        // Ensure current and next period exist
        $this->service->openPeriod(now()->format('Y-m'));
        $this->service->openPeriod(now()->addMonth()->format('Y-m'));

        $periods = FinancialPeriod::orderBy('period', 'desc')->paginate(24);

        $summary = [
            'open' => FinancialPeriod::where('status', 'open')->count(),
            'closing' => FinancialPeriod::where('status', 'closing')->count(),
            'closed' => FinancialPeriod::where('status', 'closed')->count(),
            'locked' => FinancialPeriod::where('status', 'locked')->count(),
        ];

        return view('admin.finance.periods', compact('periods', 'summary'));
    }

    // ── Show period detail / close workflow ───────────────────────────────────

    public function show(FinancialPeriod $period)
    {
        return view('admin.finance.period_show', compact('period'));
    }

    // ── Start closing a period ────────────────────────────────────────────────

    public function startClose(FinancialPeriod $period)
    {
        try {
            $this->service->startClose($period->period, Auth::id());

            return redirect()->route('admin.periods.show', $period)
                ->with('success', "Period {$period->displayLabel()} moved to 'Closing'. Complete the pre-close checklist.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Run pre-close steps ───────────────────────────────────────────────────

    public function runProvisioning(FinancialPeriod $period)
    {
        try {
            $this->service->runProvisioning($period, Auth::id());

            return back()->with('success', 'IFRS 9 provisioning complete.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function runFacilityInterest(FinancialPeriod $period)
    {
        try {
            $this->service->runFacilityInterest($period, Auth::id());

            return back()->with('success', 'Facility interest accrual complete.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markBankRecon(FinancialPeriod $period)
    {
        try {
            $this->service->markBankReconComplete($period);

            return back()->with('success', 'Bank reconciliation marked complete.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markBankReconNoActivity(FinancialPeriod $period)
    {
        try {
            $this->service->markBankReconNoActivity($period, Auth::id());

            return back()->with('success', 'Bank reconciliation marked complete — no activity confirmed for this period.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function generateTrialBalance(FinancialPeriod $period)
    {
        try {
            $data = $this->service->generateTrialBalance($period, Auth::id());

            return back()->with('success',
                'Trial balance generated. Income: R'.number_format($data['income'], 2).
                ' | Expenses: R'.number_format($data['expenses'], 2).
                ' | Net: R'.number_format($data['net_profit'], 2));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Close period ──────────────────────────────────────────────────────────

    public function close(FinancialPeriod $period)
    {
        try {
            $this->service->closePeriod($period, Auth::id());

            return redirect()->route('admin.periods.show', $period)
                ->with('success', "Period {$period->displayLabel()} closed. ".
                    ($period->is_year_end ? 'Year-end journals posted.' : ''));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Lock period ───────────────────────────────────────────────────────────

    public function lock(FinancialPeriod $period)
    {
        try {
            $this->service->lockPeriod($period, Auth::id());

            return redirect()->route('admin.periods.show', $period)
                ->with('success', "Period {$period->displayLabel()} locked. No further changes allowed.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Open a new future period manually ────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate(['period' => 'required|regex:/^\d{4}-\d{2}$/']);

        $fp = $this->service->openPeriod($request->period);

        return redirect()->route('admin.periods.show', $fp)
            ->with('success', "Period {$fp->displayLabel()} opened.");
    }
}
