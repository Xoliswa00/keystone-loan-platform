<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingsController extends Controller
{
    public function show()
    {
        $company = Company::first() ?? new Company;

        return view('admin.settings.company', compact('company'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'registration_no' => 'required|string|max:50',
            'ncr_number' => 'required|string|max:50',
            'vat_number' => 'nullable|string|max:20',
            'ncr_credit_category' => 'required|in:developmental,mortgage,unsecured,short_term',
            'physical_address' => 'required|string|max:300',
            'postal_address' => 'nullable|string|max:300',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'email' => 'required|email',
            'support_email' => 'nullable|email',
            'website' => 'nullable|url',
            'tagline' => 'nullable|string|max:200',
            'authorised_signatory' => 'required|string|max:200',
            'signatory_title' => 'nullable|string|max:100',
            'notification_from_email' => 'nullable|email',
            'notification_from_name' => 'nullable|string|max:100',
            'notification_cc' => 'nullable|string|max:500',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $company = Company::first() ?? new Company;

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('company', 'public');
        }

        unset($validated['logo']);
        $company->fill($validated);
        $company->save();

        return redirect()->route('admin.settings.company')
            ->with('success', 'Company settings saved. All agreements and notifications will now use these details.');
    }
}
