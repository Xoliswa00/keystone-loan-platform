<?php

namespace App\Http\Controllers;

use App\Models\AccountDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $accountDetails = Auth::user()->accountDetails ?? collect();

        return view('account_details.index', compact('accountDetails'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('account_details.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([

            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'numeric', 'unique:account_details,account_number'],
            'account_type' => ['required', 'in:savings,checking'],

            'payment_method' => ['required', 'in:debit_order,manual'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $validatedData['user_id'] = Auth()->user()->id;

        AccountDetail::create($validatedData);

        return redirect()->route('accountdetails.index')->with('success', 'Account details created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountDetail $accountDetail)
    {
        abort_unless($accountDetail->user_id === Auth::id(), 403);

        return view('account_details.show', compact('accountDetail'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountDetail $accountDetail)
    {
        abort_unless($accountDetail->user_id === Auth::id(), 403);

        return view('account_details.edit', compact('accountDetail'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccountDetail $accountDetail)
    {
        abort_unless($accountDetail->user_id === Auth::id(), 403);

        $validatedData = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'numeric', 'unique:account_details,account_number,'.$accountDetail->id],
            'account_type' => ['required', 'in:savings,checking'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'iban' => ['nullable', 'string', 'max:34'],
            'payment_method' => ['required', 'in:debit_order,manual'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $accountDetail->update($validatedData);

        return redirect()->route('accountdetails.index')->with('success', 'Account details updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountDetail $accountDetail)
    {
        abort_unless($accountDetail->user_id === Auth::id(), 403);

        $accountDetail->delete();

        return redirect()->route('accountdetails.index')->with('success', 'Account details deleted successfully.');
    }
}
