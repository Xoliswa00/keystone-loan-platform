<?php

namespace App\Http\Controllers;

use App\Models\RepaymentSchedule;

class RepaymentScheduleController extends Controller
{
    /**
     * repayment_schedules.index duplicated loan_repayments.index (both list
     * the same repayment schedule) but had rotted onto relations/columns
     * (loan.customer, first_name, customer_code) that don't exist on this
     * schema — LoanRepaymentController::index() is the maintained,
     * ownership-scoped implementation, so route there instead of keeping a
     * second broken query alive.
     */
    public function index()
    {
        return redirect()->route('loanrepayments.index');
    }

    /**
     * Schedules are generated automatically on loan-application approval
     * (see LoanApplicationController::approve()) — the view is a stub that
     * says so. This action used to validate 'loan_id' against the `loans`
     * table and hand it straight to RepaymentSchedule::create(), but
     * repayment_schedules.loan_id is FK'd to loan_applications — every
     * manual submission would violate the foreign key. Block it instead of
     * patching a form nothing renders.
     */
    public function create()
    {
        return view('repayment_schedules.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        return redirect()->route('repaymentSchedules.index')
            ->with('error', 'Repayment schedules are generated automatically and cannot be created manually.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RepaymentSchedule $repaymentSchedule)
    {
        abort_unless($repaymentSchedule->user_id === auth()->id(), 403);

        $repaymentSchedule->load('loanApplication');

        return view('repayment_schedules.show', compact('repaymentSchedule'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RepaymentSchedule $repaymentSchedule)
    {
        return view('repayment_schedules.edit', compact('repaymentSchedule'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RepaymentSchedule $repaymentSchedule)
    {
        return redirect()->route('repaymentSchedules.index')
            ->with('error', 'Repayment schedules are generated automatically and are read-only.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RepaymentSchedule $repaymentSchedule)
    {
        return redirect()->route('repaymentSchedules.index')
            ->with('error', 'Repayment schedules are generated automatically and cannot be deleted.');
    }
}
