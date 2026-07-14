<?php

namespace App\Http\Controllers;

use App\Models\LoanInterest;
use Illuminate\Http\Request;

class LoanInterestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $loanInterests = LoanInterest::all();

        return view('loan_interests.index', compact('loanInterests'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('loan_interests.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'interest_rate' => ['required', 'numeric', 'between:0,100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        LoanInterest::create($validatedData);

        return redirect()->route('loan_interests.index')->with('success', 'Loan interest record created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(LoanInterest $loanInterest)
    {
        return view('loan_interests.show', compact('loanInterest'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LoanInterest $loanInterest)
    {
        return view('loan_interests.edit', compact('loanInterest'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LoanInterest $loanInterest)
    {
        $validatedData = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'interest_rate' => ['required', 'numeric', 'between:0,100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $loanInterest->update($validatedData);

        return redirect()->route('loan_interests.index')->with('success', 'Loan interest record updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoanInterest $loanInterest)
    {
        $loanInterest->delete();

        return redirect()->route('loan_interests.index')->with('success', 'Loan interest record deleted successfully.');
    }
}
