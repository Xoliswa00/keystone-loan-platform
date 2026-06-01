<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Loan;
use App\Models\Loan_fee_rules;
use App\Models\LoanFee;
use App\Models\Customer;

use Illuminate\Support\Facades\DB;

use App\Models\Loans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\RepaymentSchedule;
use App\Models\LoanFeeRules;
use Illuminate\Support\Facades\Log;
    use App\Mail\LoanNotificationMail;
use Illuminate\Support\Facades\Mail;

use Carbon\Carbon;



class LoanApplicationController extends Controller
{
    // Display a list of loan applications
    public function index()
    {
        $applications = LoanApplication::where('user_id', auth()->id())
       -> where('status','<>','deleted')
        ->with('user') // Include user relationship
        ->paginate(10);
        
        return view('applications.index', ['Applications' => $applications]);
    }

public function create(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
{
    $user = Auth::user();

    /*
    |--------------------------------------------------------------------------
    | 1. Prevent Duplicate / Pending Applications
    |--------------------------------------------------------------------------
    | If the user already has an application still being processed,
    | block creation of a new one.
    */

  // Check applications still being processed
$existingApplication = LoanApplication::where('user_id', $user->id)
    ->whereIn('status', ['pending', 'under_review'])
    ->first();

if ($existingApplication) {
    return redirect()
        ->route('applications.show', $existingApplication->id)
        ->with('error', 'You currently have a pending loan application.');
}

// Check approved applications
$approvedApplication = Loan::where('user_id', $user->id)
    ->whereNotIn('status', ['Settled','rejected'])
    ->first();

if ($approvedApplication) {
    return redirect()
        ->route('applications.show', $approvedApplication->id)
        ->with('error', 'You have an active loan that hasnt been settled. In a case it was debited today, please wait for the transactions to reflect in the system.');
}



    /*
    |--------------------------------------------------------------------------
    | 2. Load User Bank Accounts
    |--------------------------------------------------------------------------
    */

    $userBankAccounts = $user->relationLoaded('accountDetails')
        ? $user->accountDetails
        : ($user->accountDetails()->exists()
            ? $user->accountDetails()->get()
            : collect());

    $hasBankAccounts = $userBankAccounts->isNotEmpty();

    /*
    |--------------------------------------------------------------------------
    | 3. Loyalty Calculation
    |--------------------------------------------------------------------------
    */

    $createdAt = $user->created_at instanceof Carbon
        ? $user->created_at
        : Carbon::parse($user->created_at ?? now()->subYears(1));

    $monthsSince = now()->diffInMonths($createdAt);

    $loyaltyStatus = $monthsSince >= 12
        ? 'loyal_returnee'
        : 'first_timer';

    /*
    |--------------------------------------------------------------------------
    | 4. Fetch Applicable Fee Rules
    |--------------------------------------------------------------------------
    */

    $rulesQuery = Loan_fee_rules::query()
        ->where('active', true)
        ->whereIn('applicability', [$loyaltyStatus, 'general'])
        ->orderByRaw("FIELD(applicability, ?, ?)", [$loyaltyStatus, 'general']);

    $rules = $rulesQuery->get()
        ->groupBy('fee_type')
        ->map(fn($group) => $group->first())
        ->toArray();

    /*
    |--------------------------------------------------------------------------
    | 5. Normalize Fee Rules for Blade / JS
    |--------------------------------------------------------------------------
    */

    $feeRules = [];

    foreach ($rules as $feeType => $ruleModel) {
        $feeRules[$feeType] = [
            'id' => $ruleModel['id'] ?? null,
            'applicability' => $ruleModel['applicability'] ?? null,
            'fee_type' => $ruleModel['fee_type'] ?? $feeType,
            'flat_fee' => isset($ruleModel['flat_fee']) ? (float) $ruleModel['flat_fee'] : null,
            'rate' => isset($ruleModel['rate']) ? (float) $ruleModel['rate'] : null,
            'cap' => isset($ruleModel['cap']) ? (float) $ruleModel['cap'] : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Return View
    |--------------------------------------------------------------------------
    */

    return view('applications.create', [
        'feeRules' => $feeRules,
        'loyaltyStartDate' => $createdAt,
        'loyaltyStatus' => $loyaltyStatus,
        'userBankAccounts' => $userBankAccounts,
        'hasBankAccounts' => $hasBankAccounts,
    ]);
}



    // Store a new loan application
  

    // Show a specific loan application
    public function show($id)
    {
       
            $loanApplication= LoanApplication::with('user')->findOrFail($id);
        return view('loans.show', compact('loanApplication'));
    }

    // Show the form for editing a loan application
    public function edit($id)
    {
            $loanApplication= LoanApplication::with('user')->findOrFail($id);
        return view('applications.edit', compact('loanApplication'));
        
    }


public function update(Request $request, $id)
{
    $request->validate([
        'loan_type' => 'required|in:personal,home,business',
        'loan_amount' => 'required|numeric|min:0',
        'purpose' => 'required|string',
        'collateral' => 'nullable|string',
        'status' => 'required|in:pending,approved,rejected',
        'approval_date' => 'nullable|date',
        'arrears' => 'required|boolean',
        'bank_statement' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'payslips' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
    ]);

    $loanApplication = LoanApplication::findOrFail($id);

    // Update basic loan details
    $loanApplication->fill([
        'loan_type' => $request->loan_type,
        'loan_amount' => $request->loan_amount,
        'purpose' => $request->purpose,
        'collateral' => $request->collateral,
        'status' => $request->status,
        'approval_date' => $request->approval_date,
        'arrears' => $request->arrears,
    ]);

    // Handle File Uploads
    if ($request->hasFile('bank_statement')) {
        $bankStatementPath = $request->file('bank_statement')->store('loan_documents', 'public');
        $loanApplication->bank_statement_path = $bankStatementPath;
    }

    if ($request->hasFile('payslips')) {
        $payslipPath = $request->file('payslips')->store('loan_documents', 'public');
        $loanApplication->payslip_path = $payslipPath;
    }

    $loanApplication->save();

    return redirect()
        ->route('applications.index')
        ->with('success', 'Loan application updated successfully with uploaded documents.');
}




public function updateNote(Request $request, $id)
{
    $request->validate([
        'reason' => 'required|string|max:1000',
    ]);

    $loanApplication = LoanApplication::findOrFail($id);
    $loanApplication->reason = $request->reason;
    $loanApplication->save();

    return back()->with('success', 'Note saved successfully.');
}





    // Delete a specific loan application
    public function destroy($id)
    {
            $application = LoanApplication::findOrFail($id);

            // Delete associated files if they exist
            $files = [
                $application->credit_score_report,
                $application->bank_statement,
                $application->payslips,
            ];

            foreach ($files as $file) {
                if ($file && Storage::exists($file)) {
                    Storage::delete($file);
                }
            }

            // Delete the record
            $application->status = 'deleted';
            $application->save();
            // Send notification before deletion
            $this->sendLoanNotification(
                $application,
                'Loan Application Deleted',
                [
                    'Your loan application has been successfully deleted from our records.',
                    'If this was a mistake, please contact our support team or reapply.'
                ],
                $application->status
            );

    return redirect()->back()->with('success', 'Loan archived successfully.');

    }



public function store(Request $request)
{
    $validated = $request->validate([
        'loan_type' => 'required|in:personal,home,business',
        'loan_amount' => 'required|numeric|min:500|max:5000',
        'months' => 'required|integer|min:1|max:12',
        'purpose' => 'nullable|string',
        'other_purpose' => 'nullable|string',
        'collateral' => 'nullable|string',
        'credit_score_report' => 'nullable|file',
        'bank_statement' => 'required|file',
        'payslips' => 'required|file',
        'terms_conditions' => 'accepted',

        'interest_rate' => 'required|numeric|min:0',
        'initiation_fee' => 'required|numeric|min:0',
        'service_fee' => 'required|numeric|min:0',
        'total_repayment' => 'required|numeric|min:0',
    ]);

    $user = Auth::user();

    DB::beginTransaction();

    try {


        // 🔒 LOCK USER ROW (STRONGER THAN LOAN LOCK)
        DB::table('users')
            ->where('id', $user->id)
            ->lockForUpdate()
            ->first();

             $existingApplication = LoanApplication::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'under_review'])
                ->first();
            
            if ($existingApplication) {
                return redirect()
                    ->route('applications.show', $existingApplication->id)
                    ->with('error', 'You currently have a pending loan application.');
            }
            
            // Check approved applications
            $approvedApplication = Loan::where('user_id', $user->id)
                ->whereNotIn('status', ['Settled','rejected'])
                ->first();
            
            if ($approvedApplication) {
                return redirect()
                    ->route('applications.show', $approvedApplication->id)
                    ->with('error', 'You have an active loan that hasnt been settled. In a case it was debited today, please wait for the transactions to reflect in the system.');
            }
        // =========================
        // FINANCIAL CALCULATIONS
        // =========================
        $loanAmount = (float) $validated['loan_amount'];
        $months = max(1, (int) $validated['months']);

        $interestRatePosted   = (float) $validated['interest_rate'] * $months;
        $initiationFeePosted  = round((float) $validated['initiation_fee'], 2);
        $serviceFeePosted     = round((float) $validated['service_fee'], 2);
        $totalRepaymentPosted = round((float) $validated['total_repayment'], 2);

        if ($totalRepaymentPosted < $loanAmount) {
            throw new \Exception('Total repayment cannot be less than loan amount.');
        }

        $interestAmountPosted = round(
            $totalRepaymentPosted - $loanAmount - $initiationFeePosted - $serviceFeePosted,
            2
        );

        if ($interestAmountPosted < 0) {
            throw new \Exception('Invalid interest calculation.');
        }

        // =========================
        // CENT DISTRIBUTION
        // =========================
        $totalCents = (int) round($totalRepaymentPosted * 100);
        $baseCents = intdiv($totalCents, $months);
        $remainder = $totalCents - ($baseCents * $months);

        $monthlyAmounts = [];
        for ($i = 0; $i < $months; $i++) {
            $cents = $baseCents + ($i < $remainder ? 1 : 0);
            $monthlyAmounts[] = $cents / 100.0;
        }

        // =========================
        // CREATE APPLICATION
        // =========================
        $application = new LoanApplication();
        $application->user_id = $user->id;
        $application->loan_type = $validated['loan_type'];
        $application->loan_amount = $loanAmount;
        $application->purpose = $validated['purpose'] ?? $validated['other_purpose'] ?? 'N/A';
        $application->collateral = $validated['collateral'] ?? null;
        $application->terms_conditions = true;
        $application->status = 'pending';
        $application->reviewer_id = $user->id;

        if ($request->hasFile('credit_score_report')) {
            $application->credit_score_report = $request->file('credit_score_report')->store('credit_score_reports', 'public');
        }
        if ($request->hasFile('bank_statement')) {
            $application->bank_statement = $request->file('bank_statement')->store('bank_statements', 'public');
        }
        if ($request->hasFile('payslips')) {
            $application->payslips = $request->file('payslips')->store('payslips', 'public');
        }

        $application->save();

        // =========================
        // SAVE FEES
        // =========================
        LoanFee::create([
            'loan_application_id' => $application->id,
            'interest_rate' => $interestRatePosted,
            'interest_amount' => $interestAmountPosted,
            'initiation_fee' => $initiationFeePosted,
            'service_fee' => $serviceFeePosted,
            'total_due' => $totalRepaymentPosted,
        ]);

        // =========================
        // SCHEDULE
        // =========================
        if (method_exists($this, 'generateRepaymentScheduleFromArray')) {
            $this->generateRepaymentScheduleFromArray($application, $monthlyAmounts);
        } else {
            $this->generateRepaymentSchedule($application, $monthlyAmounts[0], $months);
        }

        // =========================
        // CUSTOMER LOCK + CREATE
        // =========================
        DB::table('customers')
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        $existingCustomer = Customer::where('user_id', $user->id)->first();

        if (!$existingCustomer) {
            $idNumber = preg_replace('/[^0-9]/', '', $user->ID_Number ?? '000000');
            $shortId = substr($idNumber, 0, 6);

            $prefix = $user->role == 'admin' ? 'ADM' : 'CLF';
            $customerType = $user->role == 'admin' ? 'internal' : 'individual';

            Customer::create([
                'user_id' => $user->id,
                'customer_code' => $prefix . $user->id . '-' . $shortId,
                'customer_type' => $customerType,
                'payment_terms' => 'monthly',
            ]);
        }

        // =========================
        // NOTIFICATION
        // =========================
        $this->sendLoanNotification(
            $application,
            'Loan Application Created',
            [
                'Your loan application has been successfully submitted.',
                'You will be notified once reviewed.'
            ],
            $application->status
        );

        DB::commit();

        return redirect()->route('loan.index')
            ->with('success', 'Loan application submitted successfully.');

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('Loan store error: ' . $e->getMessage(), [
            'user_id' => $user->id
        ]);

        return back()->withInput()->with('error', 'Failed to submit application.');
    }
}

protected function sendLoanNotification($application, string $subject, array $bodyLines = [], ?string $status = null)
{
    if ($status) {
        array_unshift($bodyLines, "Loan Status: " . ucfirst($status));
        // Optional: include status in subject for clarity
        $subject .= " - " . ucfirst($status);
    }

    Mail::to($application->user->email)
         ->queue(new \App\Mail\LoanNotificationMail($subject, $bodyLines, $application));
}






private function generateRepaymentSchedule($loanApplication, $monthlyRepayment, $months)
{
    $user = Auth::user();
    $startDate = now();
    $repayDay = $user->salary_payment_day ?? 1;

    // 🔥 Centralized resolver
    $resolveDueDate = function ($baseDate) use ($startDate) {

        // --- Public holidays (SA basic set) ---
        $holidays = [
            '01-01','03-21','04-27','05-01','06-16',
            '08-09','09-24','12-16','12-25','12-26',
        ];

        // --- Helper: check holiday ---
        $isHoliday = function ($date) use ($holidays) {
            return in_array($date->format('m-d'), $holidays);
        };

        // --- Step 1: Weekend adjustment ---
        if ($baseDate->isSaturday()) {
            $baseDate->subDay(); // Friday
        } elseif ($baseDate->isSunday()) {
            $baseDate->subDays(2); // Friday
        } elseif ($baseDate->isMonday()) {
            $baseDate->subDays(3); // Friday
        }

        // --- Step 2: Holiday adjustment (move backwards) ---
        while ($isHoliday($baseDate)) {
            $baseDate->subDay();
        }

        // --- Step 3: Buffer logic (3 days before payday) ---
        $bufferDays = 3;
        $bufferDate = $baseDate->copy()->subDays($bufferDays);

        if ($startDate->gte($bufferDate)) {
            $baseDate->addMonth();

            // Re-adjust again after shifting month
            if ($baseDate->isSaturday()) {
                $baseDate->subDay();
            } elseif ($baseDate->isSunday()) {
                $baseDate->subDays(2);
            } elseif ($baseDate->isMonday()) {
                $baseDate->subDays(3);
            }

            while ($isHoliday($baseDate)) {
                $baseDate->subDay();
            }
        }

        return $baseDate;
    };

    // Step 1: Initial base payday
    $dueDate = $startDate->copy()->day($repayDay);

    if ($dueDate->isPast()) {
        $dueDate->addMonth();
    }

    // Step 2: Resolve first due date
    $dueDate = $resolveDueDate($dueDate);

    // Step 3: Generate schedule
    for ($i = 0; $i < $months; $i++) {

        $installmentDate = $resolveDueDate(
            $dueDate->copy()->addMonthsNoOverflow($i)
        );

        RepaymentSchedule::create([
            'loan_id' => $loanApplication->id,
            'user_id' => $user->id,
            'due_date' => $installmentDate,
            'emi_amount' => $monthlyRepayment,
            'status' => 'pending',
        ]);
    }
}


    public function adminIndex()
{
    $applications = LoanApplication::with(['user.customer'])
        ->orderBy('created_at', 'desc')
        ->paginate(15);

    return view('admin.loan_applications.index', compact('applications'));
}


  public function terms()
    {
        return view('terms'); // create resources/views/terms.blade.php
    }



}
