<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Was querying cashbook_transactions::all() — an unrelated model
        // that exposed every internal cashbook transaction across all
        // customers. Scoped to the authenticated user's own loan transactions.
        $transactions = Transaction::whereHas('loan', function ($query) {
            $query->where('user_id', Auth::id());
        })->latest('transaction_date')->get();

        return view('transactions.index', compact('transactions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('transactions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:credit,debit'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ]);

        Transaction::create($validatedData);

        return redirect()->route('transactions.index')->with('success', 'Transaction created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        abort_unless($transaction->loan?->user_id === Auth::id(), 403);

        return view('transactions.show', compact('transaction'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        abort_unless($transaction->loan?->user_id === Auth::id(), 403);

        return view('transactions.edit', compact('transaction'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->loan?->user_id === Auth::id(), 403);

        $validatedData = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:credit,debit'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ]);

        $transaction->update($validatedData);

        return redirect()->route('transactions.index')->with('success', 'Transaction updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        abort_unless($transaction->loan?->user_id === Auth::id(), 403);

        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transaction deleted successfully.');
    }
}
