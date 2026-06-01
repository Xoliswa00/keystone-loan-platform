<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
  $query = Customer::query()
        ->select('customers.*')
        ->leftJoin('users', 'customers.user_id', '=', 'users.id');

    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('customers.customer_code', 'like', "%$search%")
              ->orWhere('users.ID_Number', 'like', "%$search%")
              ->orWhere('users.phone', 'like', "%$search%")
              ->orWhere('users.name', 'like', "%$search%")
              ->orWhere('users.email', 'like', "%$search%");
        });
    }


    $customers = $query->paginate(20);

    return view('customer.index', compact('customers'));
}


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
   public function show($id)
{
    $customer = Customer::with([
        'loanApplications.loanfee',// assuming you have a documents relationship
        'loanApplications.repaymentSchedules', // each loan with its repayment schedules
        'payments',
        'cashbook_transactions',
        'loans',

        'user' // load the associated user


    ])->findOrFail($id);

    return view('customer.show', compact('customer'));
}


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
