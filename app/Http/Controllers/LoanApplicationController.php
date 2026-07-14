<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanFee;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Services\AffordabilityService;
use App\Services\CustomerLimitationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LoanApplicationController extends Controller
{
    protected AffordabilityService $affordability;

    protected CustomerLimitationService $limitations;

    public function __construct(
        AffordabilityService $affordability,
        CustomerLimitationService $limitations
    ) {
        $this->affordability = $affordability;
        $this->limitations = $limitations;
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $applications = LoanApplication::where('user_id', Auth::id())
            ->where('status', '<>', 'deleted')
            ->with('user', 'product')
            ->latest()
            ->paginate(10);

        return view('applications.index', ['Applications' => $applications]);
    }

    public function adminIndex()
    {
        $pendingApplications = LoanApplication::with(['user.customer', 'product', 'loanfee'])
            ->whereIn('status', ['pending', 'under_review'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.loan_applications.index', compact('pendingApplications'));
    }

    // ── Create (show form) ───────────────────────────────────────────────────

    public function create()
    {
        $user = Auth::user();

        // ── Comprehensive limitation check (NCR compliance) ───────────────────
        $limitCheck = $this->limitations->canApply($user);

        if (! $limitCheck['allowed']) {
            return view('applications.blocked', [
                'reason' => $limitCheck['reason'],
                'unblock_at' => $limitCheck['unblock_at'],
                'gate' => $limitCheck['gate'],
                'action_url' => $limitCheck['action_url'] ?? null,
            ]);
        }

        // Load available products — globally active, plus any extended-term
        // product this specific client has been individually granted access
        // to (see LoanProduct::scopeAvailableFor()).
        $products = LoanProduct::availableFor($user)->get();

        if ($products->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('error', 'No loan products are currently available. Please contact support.');
        }

        // Affordability snapshot for pre-qualification widget
        $affordabilityResult = $this->affordability->calculate($user);
        $profileStatus = $this->affordability->profileStatus($user);

        // Warn if profile incomplete — but still allow access so they can see the form
        $userBankAccounts = $user->accountDetails()->get();

        // Bank statement / payslip already uploaded to the KYC profile don't
        // need to be attached again on the application itself.
        $existingBankStatement = $user->customerDocuments()->where('document_type', 'bank_statement')->latest()->first();
        $existingPayslip = $user->customerDocuments()->where('document_type', 'payslip')->latest()->first();

        return view('applications.create', [
            'products' => $products,
            'affordabilityResult' => $affordabilityResult,
            'profileStatus' => $profileStatus,
            'userBankAccounts' => $userBankAccounts,
            'hasBankAccounts' => $userBankAccounts->isNotEmpty(),
            'existingBankStatement' => $existingBankStatement,
            'existingPayslip' => $existingPayslip,
        ]);
    }

    // ── Store (submit application) ───────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'loan_type' => 'required|in:personal,home,business',
            'loan_amount' => 'required|numeric|min:1',
            'loan_term_months' => 'required|integer|min:1|max:12',
            'purpose' => 'nullable|string|max:500',
            'other_purpose' => 'nullable|string|max:500',
            'collateral' => 'nullable|string|max:500',
            // Bank statement / payslip are only required here if the user hasn't
            // already uploaded one to their KYC profile — no point making them
            // attach the same document twice. A reviewer can still ask for a
            // fresh one via the document rejection flow if it's out of date.
            'bank_statement' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'payslips' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'credit_score_report' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'terms_conditions' => 'accepted',
        ]);

        $bankStatementPath = $request->hasFile('bank_statement')
            ? null
            : optional($user->customerDocuments()->where('document_type', 'bank_statement')->latest()->first())->file_path;

        $payslipPath = $request->hasFile('payslips')
            ? null
            : optional($user->customerDocuments()->where('document_type', 'payslip')->latest()->first())->file_path;

        if (! $request->hasFile('bank_statement') && ! $bankStatementPath) {
            return back()->withInput()->with('error', 'Please attach a bank statement — none is on file from your profile.');
        }

        if (! $request->hasFile('payslips') && ! $payslipPath) {
            return back()->withInput()->with('error', 'Please attach a payslip — none is on file from your profile.');
        }

        DB::beginTransaction();

        try {
            // ── 1. Lock user row + re-check all limitations inside transaction ──
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $freshUser = \App\Models\User::find($user->id); // re-read after lock

            $limitCheck = $this->limitations->canApply($freshUser);
            if (! $limitCheck['allowed']) {
                DB::rollBack();

                return back()->withInput()->with('error', $limitCheck['reason']);
            }

            // ── 2. Load and validate product ──────────────────────────────────
            $product = LoanProduct::findOrFail($validated['loan_product_id']);

            // Re-check against the same eligibility rule the create() form
            // used to build the dropdown — active for everyone, or inactive
            // but individually granted to this client. Prevents a client
            // from bypassing the dropdown by posting an arbitrary product id.
            if (! LoanProduct::availableFor($freshUser)->whereKey($product->id)->exists()) {
                DB::rollBack();

                return back()->with('error', 'This loan product is not currently available.');
            }

            $loanAmount = (float) $validated['loan_amount'];
            $months = (int) $validated['loan_term_months'];

            if ($loanAmount < $product->min_amount || $loanAmount > $product->max_amount) {
                DB::rollBack();

                return back()->withInput()
                    ->with('error', "Loan amount must be between R{$product->min_amount} and R{$product->max_amount} for this product.");
            }

            if ($months < $product->min_months || $months > $product->max_months) {
                DB::rollBack();

                return back()->withInput()
                    ->with('error', "Term must be between {$product->min_months} and {$product->max_months} months for this product.");
            }

            // ── 3. Affordability gate ─────────────────────────────────────────
            $canApply = $this->affordability->canApply($user, $product);

            if (! $canApply['allowed']) {
                DB::rollBack();

                return back()->withInput()
                    ->with('error', 'Affordability check failed: '.$canApply['reason']);
            }

            $affordResult = $this->affordability->calculate($user);

            // ── 4. Calculate repayment using product rules ────────────────────
            $repayment = $product->calculateRepayment($loanAmount, $months);

            // Verify the requested instalment is affordable
            if (! $this->affordability->passes($user, $repayment['base_instalment'])) {
                DB::rollBack();

                return back()->withInput()
                    ->with('error',
                        'The monthly instalment of R'.number_format($repayment['base_instalment'], 2).
                        ' exceeds your maximum affordable instalment of R'.
                        number_format($affordResult['max_instalment'], 2).'.');
            }

            // ── 5. Create application ─────────────────────────────────────────
            $application = LoanApplication::create([
                'user_id' => $user->id,
                'loan_product_id' => $product->id,
                'loan_type' => $validated['loan_type'],
                'loan_term_months' => $months,
                'loan_amount' => $loanAmount,
                'purpose' => $validated['purpose'] ?? $validated['other_purpose'] ?? 'Personal',
                'collateral' => $validated['collateral'] ?? null,
                'terms_conditions' => true,
                'status' => 'pending',
                'reviewer_id' => null, // assigned when admin reviews
                // Affordability snapshot — immutable audit record
                'affordability_checked' => true,
                'affordability_disposable_income' => $affordResult['disposable_income'],
                'affordability_max_instalment' => $affordResult['max_instalment'],
                'affordability_instalment_requested' => $repayment['base_instalment'],
                'credit_score_report' => $request->hasFile('credit_score_report')
                    ? $request->file('credit_score_report')->store('credit_reports', 'public')
                    : null,
                'bank_statement' => $request->hasFile('bank_statement')
                    ? $request->file('bank_statement')->store('bank_statements', 'public')
                    : $bankStatementPath,
                'payslips' => $request->hasFile('payslips')
                    ? $request->file('payslips')->store('payslips', 'public')
                    : $payslipPath,
                'nca_credit_type' => $this->classifyNcaAgreementType($loanAmount),
            ]);

            // ── 6. Save fee snapshot ──────────────────────────────────────────
            LoanFee::create([
                'loan_application_id' => $application->id,
                'interest_rate' => $product->monthly_interest_rate,
                'interest_amount' => $repayment['total_interest'],
                'initiation_fee' => $repayment['initiation_fee'],
                'service_fee' => $repayment['service_fee_total'],
                'total_due' => $repayment['total_due'],
            ]);

            // ── 7. Generate repayment schedule with correct splits ────────────
            $this->generateSchedule($application, $product, $loanAmount, $months, $repayment);

            // ── 8. Auto-create customer record if first application ───────────
            $this->ensureCustomerRecord($user);

            // ── 9. Notify ─────────────────────────────────────────────────────
            $this->sendLoanNotification(
                $application,
                'Application Received',
                [
                    'Your loan application has been submitted and is under review.',
                    'Application reference: #'.str_pad($application->id, 6, '0', STR_PAD_LEFT),
                    'Amount requested: R'.number_format($loanAmount, 2),
                    'Monthly instalment: R'.number_format($repayment['base_instalment'], 2),
                    'You will be notified once a decision is made.',
                ],
                'pending'
            );

            DB::commit();

            return redirect()->route('loan.index')
                ->with('success', 'Application submitted successfully. Reference #'.str_pad($application->id, 6, '0', STR_PAD_LEFT));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Loan application store failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Submission failed. Please try again or contact support.');
        }
    }

    // ── Show / Edit / Update ─────────────────────────────────────────────────

    public function show($id)
    {
        $loanApplication = LoanApplication::with('user', 'product', 'loanfee', 'repaymentSchedules')
            ->findOrFail($id);

        abort_unless($loanApplication->user_id === Auth::id(), 403);

        return view('loans.show', compact('loanApplication'));
    }

    public function edit($id)
    {
        $loanApplication = LoanApplication::with('user')->findOrFail($id);

        abort_unless($loanApplication->user_id === Auth::id(), 403);
        abort_unless($loanApplication->status === 'pending', 403, 'Only pending applications can be edited.');

        return view('applications.edit', compact('loanApplication'));
    }

    public function update(Request $request, $id)
    {
        $application = LoanApplication::findOrFail($id);

        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->status === 'pending', 403, 'Only pending applications can be edited.');

        // status/approval_date/arrears are staff-only fields set via the
        // admin approve/reject flow — a client editing their own pending
        // application must never be able to self-approve.
        $request->validate([
            'loan_type' => 'required|in:personal,home,business',
            'loan_amount' => 'required|numeric|min:0',
            'purpose' => 'required|string',
            'collateral' => 'nullable|string',
        ]);

        $application->fill($request->only([
            'loan_type', 'loan_amount', 'purpose', 'collateral',
        ]));

        if ($request->hasFile('bank_statement')) {
            $application->bank_statement = $request->file('bank_statement')->store('bank_statements', 'public');
        }
        if ($request->hasFile('payslips')) {
            $application->payslips = $request->file('payslips')->store('payslips', 'public');
        }

        $application->save();

        return redirect()->route('applications.index')
            ->with('success', 'Application updated.');
    }

    public function updateNote(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $application = LoanApplication::findOrFail($id);
        abort_unless($application->user_id === Auth::id(), 403);

        $application->update(['reason' => $request->reason]);

        return back()->with('success', 'Note saved.');
    }

    public function destroy($id)
    {
        $application = LoanApplication::findOrFail($id);

        abort_unless($application->user_id === Auth::id(), 403);

        foreach ([$application->credit_score_report, $application->bank_statement, $application->payslips] as $file) {
            if ($file && Storage::exists($file)) {
                Storage::delete($file);
            }
        }

        $application->update(['status' => 'deleted']);

        $this->sendLoanNotification(
            $application, 'Application Withdrawn',
            ['Your loan application has been withdrawn from our system.'],
            'deleted'
        );

        return redirect()->back()->with('success', 'Application withdrawn.');
    }

    public function terms()
    {
        return view('terms');
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Generate repayment schedule rows with correct principal / interest / fee splits.
     * For single-month loans the full amount is in one row.
     * For multi-month, each row carries its earned slice.
     */
    private function generateSchedule(
        LoanApplication $application,
        LoanProduct $product,
        float $loanAmount,
        int $months,
        array $repayment
    ): void {
        $user = Auth::user();
        $repayDay = $user->salary_payment_day ?? 25;
        $today = now();

        // SA public holidays (month-day format)
        $holidays = ['01-01', '03-21', '04-27', '05-01', '06-16', '08-09', '09-24', '12-16', '12-25', '12-26'];

        $resolveDate = function (Carbon $base) use ($today, $holidays): Carbon {
            // Move weekend to preceding Friday
            if ($base->isSaturday()) {
                $base->subDay();
            } elseif ($base->isSunday()) {
                $base->subDays(2);
            } elseif ($base->isMonday()) {
                $base->subDays(3);
            }

            // Move holiday backwards
            while (in_array($base->format('m-d'), $holidays)) {
                $base->subDay();
            }

            // If we're within 3 days of payday, push to next month
            if ($today->gte($base->copy()->subDays(3))) {
                $base->addMonthNoOverflow();
                if ($base->isSaturday()) {
                    $base->subDay();
                } elseif ($base->isSunday()) {
                    $base->subDays(2);
                } elseif ($base->isMonday()) {
                    $base->subDays(3);
                }
                while (in_array($base->format('m-d'), $holidays)) {
                    $base->subDay();
                }
            }

            return $base;
        };

        $firstDue = $resolveDate($today->copy()->day($repayDay)->isPast()
            ? $today->copy()->addMonthNoOverflow()->day($repayDay)
            : $today->copy()->day($repayDay));

        for ($i = 0; $i < $months; $i++) {
            $dueDate = $resolveDate($firstDue->copy()->addMonthsNoOverflow($i));

            $split = $product->installmentSplit(
                $loanAmount, $months, $i,
                $repayment['base_instalment'],
                $repayment['rounding_remainder']
            );

            RepaymentSchedule::create([
                'loan_id' => $application->id,
                'user_id' => $user->id,
                'installment_number' => $i + 1,
                'due_date' => $dueDate->toDateString(),
                'emi_amount' => $split['emi_amount'],
                'principal_amount' => $split['principal_amount'],
                'interest_amount' => $split['interest_amount'],
                'fee_amount' => $split['fee_amount'],
                'status' => 'pending',
            ]);
        }
    }

    /**
     * NCA agreement-size categorisation, by the statutory principal-debt
     * thresholds (small ≤ R15,000, intermediate ≤ R250,000, large above).
     * 'developmental' and 'incidental' are separate purpose-based categories
     * (education/low-income housing/small-business finance; deferred-payment
     * fee arrangements) that don't apply to this lender's unsecured personal/
     * business microloans, so they're intentionally never returned here —
     * distinct from LoanAgreementService::classifyNcaCreditType(), which
     * returns a human-readable label for the agreement PDF, not this enum.
     */
    private function classifyNcaAgreementType(float $principal): string
    {
        return match (true) {
            $principal <= 15000 => 'small_agreement',
            $principal <= 250000 => 'intermediate_agreement',
            default => 'large_agreement',
        };
    }

    private function ensureCustomerRecord(\App\Models\User $user): void
    {
        if (Customer::where('user_id', $user->id)->exists()) {
            return;
        }

        $prefix = ($user->rule_id === 2) ? 'ADM' : 'KYC'.auth()->id(); // Example prefix for internal users; adjust as needed

        Customer::create([
            'user_id' => $user->id,
            'customer_code' => Customer::generateCode($prefix),
            'customer_type' => ($user->rule_id === 2) ? 'internal' : 'individual',
            'payment_terms' => 'monthly',
        ]);
    }

    protected function sendLoanNotification($application, string $subject, array $bodyLines = [], ?string $status = null): void
    {
        if ($status) {
            array_unshift($bodyLines, 'Status: '.ucfirst($status));
        }

        try {
            Mail::to($application->user->email)
                ->queue(new \App\Mail\LoanNotificationMail($subject, $bodyLines, $application));
        } catch (\Exception $e) {
            Log::warning('Loan notification failed: '.$e->getMessage());
        }
    }
}
