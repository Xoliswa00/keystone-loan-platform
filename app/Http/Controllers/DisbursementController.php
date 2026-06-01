<?php
namespace App\Http\Controllers;

use App\Models\LoanDisbursement;
use App\Services\DisbursementService;
use App\Models\LoanApplication;

use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
    use App\Mail\LoanNotificationMail;
use Illuminate\Support\Facades\Mail;

class DisbursementController extends Controller
{
protected $disbursementService;

    public function __construct(DisbursementService $disbursementService)
    {
        $this->disbursementService = $disbursementService;
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch all disbursements
        $disbursements = loanDisbursement::with('loan', 'approver')->get();
        return view('disbursements.index', compact('disbursements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Loan $loan)
    {
        // Only allow creation of disbursement if the loan is approved
        if ($loan->status !== 'approved') {
            return redirect()->route('loans.index')->with('error', 'Loan not approved for disbursement.');
        }

        return view('disbursements.create', compact('loan'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the disbursement request
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'disbursed_amount' => ['required', 'numeric', 'min:0.01'],
            'disbursement_method' => ['required', 'in:bank_transfer,cash,check,other'],
            'disbursement_date' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string'],
            'proof_of_payment' => ['nullable', 'file', 'mimes:jpg,png,pdf'],
        ]);
    
        // Capture the approver's ID (logged-in user)
        $validatedData['approver_id'] = auth()->id();
    
        // Handle file upload for proof of payment
        if ($request->hasFile('proof_of_payment')) {
            // Store the uploaded file and get its path
            $proofOfPaymentPath = $request->file('proof_of_payment')->store('proofs', 'public');
            // Add the file path to the validated data
            $validatedData['proof_of_payment'] = $proofOfPaymentPath;
        }
    
        // Create a new loan disbursement
        $disbursement = LoanDisbursement::create($validatedData);
    
        // Update the loan status to 'disbursed' after disbursement
        $loan = Loan::findOrFail($validatedData['loan_id']);
        $loan->status = 'disbursed';
        $loan->disbursed_at = $validatedData['disbursement_date'];  // Optional: Set disbursement date
        $loan->save();
    
        // Redirect to loan listings with success message
        return redirect()->route('loans.index')->with('success', 'Loan disbursed successfully.');
    }
    

    /**
     * Display the specified resource.
     */
    public function show(loanDisbursement $disbursement)
    {
        // Show detailed disbursement information
        return view('disbursements.show', compact('disbursement'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(loanDisbursement $disbursement)
    {
        // Only allow editing if the disbursement is not processed
        if ($disbursement->status === 'processed') {
            return redirect()->route('disbursements.index')->with('error', 'Processed disbursements cannot be edited.');
        }

        return view('disbursements.edit', compact('disbursement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, loanDisbursement $disbursement)
    {
        // Validate the update request
        $validatedData = $request->validate([
            'disbursed_amount' => ['required', 'numeric', 'min:0.01'],
            'disbursement_method' => ['required', 'in:bank_transfer,cash,check,other'],
            'disbursement_date' => ['required', 'date'],
        ]);

        // Update disbursement details
        $disbursement->update($validatedData);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(loanDisbursement $disbursement)
    {
        // Only allow deletion if the disbursement is not processed
        if ($disbursement->status === 'processed') {
            return redirect()->route('disbursements.index')->with('error', 'Processed disbursements cannot be deleted.');
        }

        // Delete disbursement
        $disbursement->delete();

        return redirect()->route('disbursements.index')->with('success', 'Disbursement deleted successfully.');
    }


public function approve($id)
{
    $user = auth()->user();

    try {
        $disbursement = $this->disbursementService->approveAndPost($id, $user->id);

        $application = $disbursement->loan->loanApplication;

        if ($application) {
            $this->sendLoanNotification(
                $application,
                'Loan Application Paid',
                [
                    'Your loan amount has been successfully paid into your account.',
                    'You can now view your repayment schedule in your dashboard.'
                ],
                $application->status
            );
        }

        return redirect()->back()
            ->with('success', 'Disbursement approved, GL posted, and notification sent.');

    } catch (\Exception $e) {

        Log::error('Disbursement approve error', [
            'id' => $id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()
            ->with('error', 'Failed to approve disbursement: ' . $e->getMessage());
    }
}

public function reject(Request $request, $id)
{
    $disbursement = LoanDisbursement::findOrFail($id);


    if ($disbursement->status !== 'waiting_for_approval') {
        return back()->with('error', 'This disbursement cannot be rejected.');
    }

    $disbursement->update([
        'status' => 'rejected',
        'rejected_by' => Auth::id(),
        'rejected_at' => now(),
        'rejection_reason' => $request->input('rejection_reason', 'No reason provided'),
    ]);

    if ($disbursement->loanApplication && $disbursement->loanApplication->user) {
        $this->sendLoanNotification(
            $disbursement->loanApplication,
            'Loan Disbursement Rejected',
            [
                "Your loan disbursement (#{$disbursement->id}) has been rejected.",
                "Rejected by: " . Auth::user()->name . " (User ID: " . Auth::id() . ")",
                "Reason: " . $disbursement->rejection_reason,
            ],
            'rejected'
        );
    }

    return back()->with('success', 'Disbursement rejected and notification sent.');
}

public function release($id)
{
    $disbursement = LoanDisbursement::findOrFail($id);


    if ($disbursement->status !== 'approved') {
        return back()->with('error', 'This disbursement cannot be released.');
    }

    $disbursement->update([
        'status' => 'released',
        'released_by' => Auth::id(),
        'released_at' => now(),
    ]);

    if ($disbursement->loanApplication && $disbursement->loanApplication->user) {
        $this->sendLoanNotification(
            $disbursement->loanApplication,
            'Loan Disbursement Released',
            [
                "Your loan disbursement (#{$disbursement->id}) has been released.",
                "Released by: " . Auth::user()->name . " (User ID: " . Auth::id() . ")",
                "Amount: R" . number_format($disbursement->amount, 2),
            ],
            'released'
        );
    }

    return back()->with('success', 'Funds released and notification sent.');
}

public function approveAll()
{
    $pending = LoanDisbursement::where('status', 'waiting_for_approval')->get();


    if ($pending->isEmpty()) {
        return back()->with('error', 'No pending disbursements found.');
    }

    foreach ($pending as $disbursement) {
        $disbursement->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if ($disbursement->loanApplication && $disbursement->loanApplication->user) {
            $this->sendLoanNotification(
                $disbursement->loanApplication,
                'Loan Disbursement Approved',
                [
                    "Your loan disbursement (#{$disbursement->id}) has been approved.",
                    "Approved by: " . Auth::user()->name . " (User ID: " . Auth::id() . ")",
                    "Amount: R" . number_format($disbursement->amount, 2),
                ],
                'approved'
            );
        }
    }

    return back()->with('success', count($pending) . ' disbursement(s) approved and notifications sent.');
}


protected function sendLoanNotification($application, string $subject, array $bodyLines = [], ?string $status = null)
{
    if (!$application->user || !$application->user->email) {
        Log::warning('Loan notification skipped: missing user or email', [
            'application_id' => $application->id ?? null
        ]);
        return;
    }

    if ($status) {
        array_unshift($bodyLines, "Loan Status: " . ucfirst($status));
        $subject .= " - " . ucfirst($status);
    }

    Mail::to($application->user->email)
        ->send(new LoanNotificationMail($subject, $bodyLines, $application));

    Log::info('Loan notification email sent', [
        'application_id' => $application->id,
        'email' => $application->user->email
    ]);
}



}
