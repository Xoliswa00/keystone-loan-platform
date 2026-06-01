<?php

namespace App\Http\Controllers;

use App\Models\RepaymentSchedule;
use App\Models\Loan;
use Illuminate\Http\Request;
USE App\Models\LoanRepayment;

class RepaymentScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    $query = LoanRepayment::with(['loan.customer'])
        ->orderBy('repayment_date', 'desc')->where('status','!=','rejected');

    // If the user is not admin, only show their repayments
    if (auth()->user()->role !== 'admin') {
        $query->whereHas('loan.customer', function ($q) {
            $q->where('user_id', auth()->id());
        });
    }

    // 🔍 Handle search across customer name, reference, or loan code
    if ($request->filled('search')) {
        $search = trim($request->search);
        $query->where(function ($q) use ($search) {
            $q->where('reference', 'like', "%{$search}%")
              ->orWhere('payment_method', 'like', "%{$search}%")
              ->orWhereHas('loan.customer', function ($sub) use ($search) {
                  $sub->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('customer_code', 'like', "%{$search}%");
              });
        });
    }

    $repayments = $query->paginate(50)->withQueryString();

    return view('repayment_schedules.index', compact('repayments'));
}




    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $loans = Loan::all();
        return view('repayment_schedules.create', compact('loans'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the incoming data with appropriate rules
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'due_date' => ['required', 'date'],
            'emi_amount' => ['required', 'numeric', 'min:0.01'], // Ensure consistency with migration field name
            'status' => ['required', 'in:pending,paid,overdue'],
        ]);

        // Create a new repayment schedule
        RepaymentSchedule::create($validatedData);

        // Redirect to the index with a success message
        return redirect()->route('repayment_schedules.index')->with('success', 'Repayment schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RepaymentSchedule $repaymentSchedule)
    {
        // Ensure the loan relationship is loaded
        $repaymentSchedule->load('loan');
        return view('repayment_schedules.show', compact('repaymentSchedule'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RepaymentSchedule $repaymentSchedule)
    {
        $loans = Loan::all();
        return view('repayment_schedules.edit', compact('repaymentSchedule', 'loans'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RepaymentSchedule $repaymentSchedule)
    {
        // Validate the incoming data
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'due_date' => ['required', 'date'],
            'emi_amount' => ['required', 'numeric', 'min:0.01'], // Ensure consistency with migration field name
            'status' => ['required', 'in:pending,paid,overdue'],
        ]);

        // Update the repayment schedule with validated data
        $repaymentSchedule->update($validatedData);

        // Redirect to the index with a success message
        return redirect()->route('repayment_schedules.index')->with('success', 'Repayment schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RepaymentSchedule $repaymentSchedule)
    {
        // Delete the repayment schedule
        $repaymentSchedule->delete();

        // Redirect to the index with a success message
        return redirect()->route('repayment_schedules.index')->with('success', 'Repayment schedule deleted successfully.');
    }
}
