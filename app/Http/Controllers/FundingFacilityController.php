<?php

namespace App\Http\Controllers;

use App\Models\FundingFacility;
use App\Models\FundingFacilityTransaction;
use App\Services\FundingFacilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FundingFacilityController extends Controller
{
    protected FundingFacilityService $service;

    public function __construct(FundingFacilityService $service)
    {
        $this->service = $service;
    }

    // ── List all facilities ───────────────────────────────────────────────────

    public function index()
    {
        $facilities = FundingFacility::with('transactions')->orderBy('status')->orderBy('funder_name')->get();

        $summary = [
            'total_facilities'   => $facilities->count(),
            'total_drawn'        => $facilities->sum('current_balance'),
            'total_accrued_int'  => $facilities->sum('accrued_interest'),
            'total_limit'        => $facilities->sum('facility_limit'),
            'active_count'       => $facilities->where('status','active')->count(),
        ];

        return view('admin.funding.index', compact('facilities', 'summary'));
    }

    // ── Show single facility ──────────────────────────────────────────────────

    public function show(FundingFacility $fundingFacility)
    {
        $fundingFacility->load(['transactions' => fn($q) => $q->orderBy('transaction_date','desc'), 'createdBy']);

        $monthlyInterest = $fundingFacility->monthlyInterestDue();

        // Cost of funds rate vs portfolio yield — for management reporting
        $portfolioYield = DB::table('repayment_schedules')
            ->where('status','paid')
            ->whereMonth('paid_at', now()->month)
            ->sum(DB::raw('interest_amount + fee_amount'));

        return view('admin.funding.show', compact('fundingFacility', 'monthlyInterest', 'portfolioYield'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        return view('admin.funding.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'facility_name'        => 'required|string|max:200',
            'funder_type'          => 'required|in:shareholder_loan,bank_credit_line,private_investor,family_office,other',
            'funder_name'          => 'required|string|max:200',
            'funder_id_or_reg'     => 'nullable|string|max:20',
            'contact_person'       => 'nullable|string|max:100',
            'contact_email'        => 'nullable|email',
            'contact_phone'        => 'nullable|string|max:20',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:30',
            'facility_limit'       => 'required|numeric|min:1',
            'interest_rate'        => 'required|numeric|min:0|max:1',
            'interest_type'        => 'required|in:fixed,variable,prime_plus',
            'prime_margin'         => 'nullable|numeric',
            'facility_start_date'  => 'nullable|date',
            'maturity_date'        => 'nullable|date|after:facility_start_date',
            'payment_frequency'    => 'required|in:monthly,quarterly,bi-annual,annual,on_demand',
            'payment_day'          => 'nullable|integer|min:1|max:31',
            'collateral_description' => 'nullable|string',
            'notes'                => 'nullable|string',
            'agreement_document'   => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status']     = 'active';

        // Auto-generate facility code
        $last = FundingFacility::withTrashed()->max('id') ?? 0;
        $validated['facility_code'] = 'FF-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);

        if ($request->hasFile('agreement_document')) {
            $validated['agreement_document_path'] = $request->file('agreement_document')
                ->store('facility_agreements', 'public');
            unset($validated['agreement_document']);
        }

        $facility = FundingFacility::create($validated);

        return redirect()->route('admin.funding.show', $facility)
            ->with('success', "Funding facility {$facility->facility_code} created.");
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(FundingFacility $fundingFacility)
    {
        return view('admin.funding.edit', compact('fundingFacility'));
    }

    public function update(Request $request, FundingFacility $fundingFacility)
    {
        $validated = $request->validate([
            'facility_name'         => 'required|string|max:200',
            'funder_name'           => 'required|string|max:200',
            'contact_person'        => 'nullable|string|max:100',
            'contact_email'         => 'nullable|email',
            'contact_phone'         => 'nullable|string|max:20',
            'facility_limit'        => 'required|numeric|min:1',
            'interest_rate'         => 'required|numeric|min:0|max:1',
            'interest_type'         => 'required|in:fixed,variable,prime_plus',
            'payment_frequency'     => 'required|in:monthly,quarterly,bi-annual,annual,on_demand',
            'payment_day'           => 'nullable|integer|min:1|max:31',
            'status'                => 'required|in:active,paused,fully_repaid,in_default,closed',
            'maturity_date'         => 'nullable|date',
            'collateral_description'=> 'nullable|string',
            'notes'                 => 'nullable|string',
        ]);

        $fundingFacility->update($validated);

        return redirect()->route('admin.funding.show', $fundingFacility)
            ->with('success', 'Facility updated.');
    }

    // ── Record transaction (drawdown / repayment / interest payment) ──────────

    public function recordTransaction(Request $request, FundingFacility $fundingFacility)
    {
        $request->validate([
            'transaction_type' => 'required|in:drawdown,repayment,interest_payment',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        $amount = (float) $request->amount;
        $date   = $request->transaction_date;
        $ref    = $request->reference ?? 'Manual entry';
        $userId = Auth::id();
        $notes  = $request->notes;

        try {
            $txn = match($request->transaction_type) {
                'drawdown'         => $this->service->recordDrawdown($fundingFacility, $amount, $date, $ref, $userId, $notes),
                'repayment'        => $this->service->recordRepayment($fundingFacility, $amount, $date, $ref, $userId, $notes),
                'interest_payment' => $this->service->payInterest($fundingFacility, $amount, $date, $ref, $userId, $notes),
            };

            return redirect()->route('admin.funding.show', $fundingFacility)
                ->with('success', ucfirst(str_replace('_',' ',$txn->transaction_type)) . ' of R' . number_format($amount,2) . ' recorded and posted to GL.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Transaction failed: ' . $e->getMessage());
        }
    }

    // ── Accrue interest for all active facilities ─────────────────────────────

    public function accrueAll()
    {
        $results = $this->service->accrueAllActive(Auth::id());

        $msg = "Interest accrued for {$results['accrued']} facility/facilities.";
        if (!empty($results['errors'])) {
            $msg .= ' Errors: ' . implode(', ', $results['errors']);
        }

        return redirect()->route('admin.funding.index')
            ->with('success', $msg);
    }

    // ── Upload facility agreement ─────────────────────────────────────────────

    public function uploadAgreement(Request $request, FundingFacility $fundingFacility)
    {
        $request->validate(['agreement' => 'required|file|mimes:pdf|max:10240']);

        $path = $request->file('agreement')->store('facility_agreements', 'public');
        $fundingFacility->update(['agreement_document_path' => $path]);

        return back()->with('success', 'Facility agreement uploaded.');
    }
}
