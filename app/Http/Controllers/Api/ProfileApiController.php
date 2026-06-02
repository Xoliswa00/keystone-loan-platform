<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Services\AffordabilityService;
use Illuminate\Http\Request;

class ProfileApiController extends Controller
{
    protected AffordabilityService $affordability;

    public function __construct(AffordabilityService $affordability)
    {
        $this->affordability = $affordability;
    }

    public function show(Request $request)
    {
        $user    = $request->user()->load('customerProfile', 'customerDocuments', 'customer');
        $status  = $this->affordability->profileStatus($user);
        $result  = $this->affordability->calculate($user);
        $profile = $user->customerProfile;

        return response()->json([
            'user' => [
                'name'         => $user->name,
                'email'        => $user->email,
                'phone'        => $user->phone,
                'id_number'    => $user->ID_Number,
                'address'      => $user->address,
                'salary_day'   => $user->salary_payment_day,
            ],
            'profile' => $profile ? [
                'employment_type'      => $profile->employment_type,
                'employment_tenure'    => $profile->employment_tenure,
                'employer_name'        => $profile->employer_name,
                'net_monthly_income'   => (float) ($profile->net_monthly_income ?? 0),
                'other_income'         => (float) ($profile->other_income ?? 0),
                'expense_housing'      => (float) ($profile->expense_housing ?? 0),
                'expense_transport'    => (float) ($profile->expense_transport ?? 0),
                'expense_existing_debt'=> (float) ($profile->expense_existing_debt ?? 0),
                'expense_insurance'    => (float) ($profile->expense_insurance ?? 0),
                'expense_living'       => (float) ($profile->expense_living ?? 0),
                'disposable_income'    => (float) ($profile->disposable_income ?? 0),
                'max_monthly_instalment'=> (float) ($profile->max_monthly_instalment ?? 0),
                'profile_complete'     => (bool) $profile->profile_complete,
                'bank_risk_flag'       => $profile->bank_statement_risk_flag,
                'avg_days_to_zero'     => $profile->avg_days_to_zero,
            ] : null,
            'affordability' => [
                'eligible'          => $result['eligible'],
                'disposable_income' => (float) ($result['disposable_income'] ?? 0),
                'max_instalment'    => (float) ($result['max_instalment'] ?? 0),
                'reason'            => $result['reason'] ?? null,
            ],
            'profile_status' => [
                'percentage' => $status['percentage'],
                'complete'   => $status['complete'],
                'missing'    => $status['missing'],
            ],
            'documents' => $user->customerDocuments->map(fn($d) => [
                'type'      => $d->document_type,
                'label'     => $d->getTypeLabel(),
                'verified'  => $d->verified,
                'uploaded_at' => $d->created_at,
            ]),
        ]);
    }

    public function updateIncome(Request $request)
    {
        $validated = $request->validate([
            'employment_type'       => 'required|in:permanent,contract,self_employed,unemployed',
            'net_monthly_income'    => 'required|numeric|min:0',
            'other_income'          => 'nullable|numeric|min:0',
            'expense_housing'       => 'required|numeric|min:0',
            'expense_transport'     => 'required|numeric|min:0',
            'expense_existing_debt' => 'required|numeric|min:0',
            'expense_insurance'     => 'required|numeric|min:0',
            'expense_living'        => 'required|numeric|min:0',
        ]);

        $user    = $request->user();
        $profile = CustomerProfile::firstOrNew(['user_id' => $user->id]);
        $profile->fill(array_merge($validated, ['user_id' => $user->id]));
        $profile->save();
        $profile->recalculate();

        return response()->json([
            'message'               => 'Profile updated.',
            'disposable_income'     => (float) $profile->disposable_income,
            'max_monthly_instalment'=> (float) $profile->max_monthly_instalment,
        ]);
    }
}
