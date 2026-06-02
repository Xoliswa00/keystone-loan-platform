<?php

namespace App\Http\Controllers;

use App\Models\CustomerProfile;
use App\Models\CustomerDocument;
use App\Services\AffordabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CustomerProfileController extends Controller
{
    protected AffordabilityService $affordability;

    public function __construct(AffordabilityService $affordability)
    {
        $this->affordability = $affordability;
    }

    /**
     * Show the profile completion page.
     */
    public function show()
    {
        $user    = Auth::user();
        $profile = $user->customerProfile ?? new CustomerProfile(['user_id' => $user->id]);

        $affordabilityResult = $this->affordability->calculate($user);
        $profileStatus       = $this->affordability->profileStatus($user);
        $documents           = $user->customerDocuments()->get()->keyBy('document_type');

        return view('profile.customer', compact(
            'user', 'profile', 'affordabilityResult', 'profileStatus', 'documents'
        ));
    }

    /**
     * Save income and expense details — recalculates affordability automatically.
     */
    public function saveAffordability(Request $request)
    {
        $validated = $request->validate([
            'employer_name'          => 'nullable|string|max:200',
            'employer_phone'         => 'nullable|string|max:20',
            'employment_type'        => 'required|in:permanent,contract,self_employed,unemployed',
            'employment_tenure'      => 'required|in:less_than_6m,6m_to_1y,1y_to_3y,over_3y',
            'gross_monthly_income'   => 'nullable|numeric|min:0',
            'net_monthly_income'     => 'required|numeric|min:0',
            'other_income'           => 'nullable|numeric|min:0',
            'other_income_source'    => 'nullable|string|max:200',
            'expense_housing'        => 'required|numeric|min:0',
            'expense_transport'      => 'required|numeric|min:0',
            'expense_existing_debt'  => 'required|numeric|min:0',
            'expense_insurance'      => 'required|numeric|min:0',
            'expense_living'         => 'required|numeric|min:0',
            'marital_status'         => 'nullable|in:single,married,divorced,widowed',
            'dependants'             => 'nullable|integer|min:0|max:20',
        ]);

        $user    = Auth::user();
        $profile = CustomerProfile::firstOrNew(['user_id' => $user->id]);
        $profile->fill($validated);
        $profile->user_id = $user->id;
        $profile->save();

        // Recalculate derived fields
        $profile->recalculate();

        return back()->with('success', 'Financial profile saved. Your pre-qualification has been updated.');
    }

    /**
     * Upload a KYC document (replace existing of same type).
     */
    public function uploadDocument(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:id_document,payslip,bank_statement,proof_of_address,other',
            'document'      => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_date' => 'nullable|date|before_or_equal:today',
        ]);

        $user = Auth::user();

        // Soft-delete any existing document of this type
        CustomerDocument::where('user_id', $user->id)
            ->where('document_type', $validated['document_type'])
            ->each(fn($d) => $d->delete());

        $file = $request->file('document');
        $path = $file->store("customer_documents/{$user->id}", 'public');

        CustomerDocument::create([
            'user_id'       => $user->id,
            'document_type' => $validated['document_type'],
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'document_date' => $validated['document_date'] ?? null,
            'verified'      => false,
        ]);

        return back()->with('success', ucfirst(str_replace('_', ' ', $validated['document_type'])) . ' uploaded successfully.');
    }

    /**
     * Return pre-qualification JSON — used by the live affordability widget.
     */
    public function preQualification()
    {
        $user   = Auth::user();
        $result = $this->affordability->calculate($user);
        $status = $this->affordability->profileStatus($user);

        return response()->json([
            'eligible'          => $result['eligible'],
            'disposable_income' => $result['disposable_income'],
            'max_instalment'    => $result['max_instalment'],
            'profile_pct'       => $status['percentage'],
            'missing'           => $status['missing'],
        ]);
    }

    /**
     * Admin: verify a customer document.
     */
    public function verifyDocument(Request $request, CustomerDocument $document)
    {
        $request->validate([
            'verified'         => 'required|boolean',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $document->update([
            'verified'         => $request->verified,
            'verified_by'      => Auth::id(),
            'verified_at'      => $request->verified ? now() : null,
            'rejection_reason' => $request->verified ? null : $request->rejection_reason,
        ]);

        return back()->with('success', 'Document ' . ($request->verified ? 'verified' : 'rejected') . '.');
    }
}
