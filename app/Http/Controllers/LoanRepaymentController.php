<?php

namespace App\Http\Controllers;

use App\Models\LoanRepayment;
use App\Models\Loan;
use Illuminate\Http\Request;
USE App\Models\RepaymentSchedule;

class LoanRepaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
   $query = RepaymentSchedule::select('repayment_schedules.*', 'loans.user_id')
        ->join('loans', 'repayment_schedules.loan_id', '=', 'loans.loan_application_id')
        ->with('loan')
        ->where('repayment_schedules.status', '!=','rejected')
        ->orderBy('repayment_schedules.due_date', 'asc');

    // If NOT admin/finance role (rule_id != 2), limit to own loans
    if (auth()->user()->rule_id !== 2) {
        $query->where('loans.user_id', auth()->user()->id);
    }

    $loanRepayments = $query->get();

        return view('loan_repayments.index', compact('loanRepayments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $loans = Loan::all();
        return view('loan_repayments.create', compact('loans'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'repayment_date' => ['required', 'date'],
            'status' => ['required', 'in:paid,pending,overdue'],
            'payment_method' => ['required', 'in:bank_transfer,cash,card,mobile_payment'], // New field
            'payment_reference' => ['nullable', 'string', 'max:255'], // New field
            'total_paid' => ['nullable', 'numeric', 'min:0'], // New field
            'notes' => ['nullable', 'string'], // New field
        ]);

        // Store the loan repayment record
        LoanRepayment::create($validatedData);

        return redirect()->route('loan_repayments.index')->with('success', 'Loan repayment record created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(LoanRepayment $loanRepayment)
    {
        return view('loan_repayments.show', compact('loanRepayment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LoanRepayment $loanRepayment)
    {
        $loans = Loan::all();
        return view('loan_repayments.edit', compact('loanRepayment', 'loans'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LoanRepayment $loanRepayment)
    {
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'repayment_date' => ['required', 'date'],
            'status' => ['required', 'in:paid,pending,overdue'],
            'payment_method' => ['required', 'in:bank_transfer,cash,card,mobile_payment'], // New field
            'payment_reference' => ['nullable', 'string', 'max:255'], // New field
            'total_paid' => ['nullable', 'numeric', 'min:0'], // New field
            'notes' => ['nullable', 'string'], // New field
        ]);

        // Update the loan repayment record
        $loanRepayment->update($validatedData);

        return redirect()->route('loan_repayments.index')->with('success', 'Loan repayment record updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoanRepayment $loanRepayment)
    {
        $loanRepayment->delete();

        return redirect()->route('loan_repayments.index')->with('success', 'Loan repayment record deleted successfully.');
    }
}
