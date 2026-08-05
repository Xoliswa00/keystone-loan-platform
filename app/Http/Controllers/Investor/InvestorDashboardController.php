<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\FundingFacility;
use Illuminate\Http\Request;

class InvestorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $idNumber = $request->session()->get('investor_id_number');
        $email = $request->session()->get('investor_email');

        $facilities = FundingFacility::whereRaw('LOWER(funder_id_or_reg) = ?', [strtolower($idNumber)])
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($email)])
            ->with(['transactions' => fn ($q) => $q->orderByDesc('transaction_date')])
            ->get();

        return view('investor.dashboard', compact('facilities'));
    }
}
