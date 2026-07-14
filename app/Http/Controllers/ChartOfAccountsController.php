<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storechart_of_accountsRequest;
use App\Http\Requests\Updatechart_of_accountsRequest;
use App\Models\chart_of_accounts;

class ChartOfAccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(Storechart_of_accountsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(chart_of_accounts $chart_of_accounts)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(chart_of_accounts $chart_of_accounts)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Updatechart_of_accountsRequest $request, chart_of_accounts $chart_of_accounts)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(chart_of_accounts $chart_of_accounts)
    {
        //
    }
}
