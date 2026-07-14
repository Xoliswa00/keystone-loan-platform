<?php

namespace App\Http\Controllers;

use App\Jobs\CheckDocumentContentJob;
use App\Models\CustomerDocument;
use App\Models\CustomerProfile;
use App\Models\LoanApplication;
use App\Services\AffordabilityService;
use App\Services\BankStatementAnalysisService;
use App\Services\CloDecisionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerProfileController extends Controller
{
    protected AffordabilityService $affordability;

    protected BankStatementAnalysisService $bankAnalysis;

    public function __construct(
        AffordabilityService $affordability,
        BankStatementAnalysisService $bankAnalysis
    ) {
        $this->affordability = $affordability;
        $this->bankAnalysis = $bankAnalysis;
    }

    /**
     * Show the profile completion page.
     */
    public function show()
    {
        $user = Auth::user();
        $profile = $user->customerProfile ?? new CustomerProfile(['user_id' => $user->id]);

        $affordabilityResult = $this->affordability->calculate($user);
        $profileStatus = $this->affordability->profileStatus($user);
        $documents = $user->customerDocuments()->orderByDesc('created_at')->get()->groupBy('document_type');
        $accountDetails = $user->accountDetails()->orderByDesc('created_at')->get();

        return view('profile.customer', compact(
            'user', 'profile', 'affordabilityResult', 'profileStatus', 'documents', 'accountDetails'
        ));
    }

    /**
     * Save income and expense details — recalculates affordability automatically.
     */
    public function saveAffordability(Request $request)
    {
        $validated = $request->validate([
            'employer_name' => 'nullable|string|max:200',
            'employer_phone' => 'nullable|string|max:20',
            'employment_type' => 'required|in:permanent,contract,self_employed,unemployed',
            'employment_tenure' => 'required|in:less_than_6m,6m_to_1y,1y_to_3y,over_3y',
            'gross_monthly_income' => 'nullable|numeric|min:0',
            'net_monthly_income' => 'required|numeric|min:0',
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
            'expense_housing' => 'required|numeric|min:0',
            'expense_transport' => 'required|numeric|min:0',
            'expense_existing_debt' => 'required|numeric|min:0',
            'expense_insurance' => 'required|numeric|min:0',
            'expense_living' => 'required|numeric|min:0',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'dependants' => 'nullable|integer|min:0|max:20',
        ]);

        $user = Auth::user();
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
     * Bank statements are automatically analysed after upload.
     */
    public function uploadDocument(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:id_document,payslip,bank_statement,proof_of_address,other',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_date' => 'nullable|date|before_or_equal:today',
        ]);

        $user = Auth::user();

        // Bank statements accumulate — a customer can bank with more than one
        // institution, and each bank issues its own separate statement file.
        // Every other document type is naturally single, so a new upload
        // replaces the old one.
        if ($validated['document_type'] !== 'bank_statement') {
            CustomerDocument::where('user_id', $user->id)
                ->where('document_type', $validated['document_type'])
                ->each(fn ($d) => $d->delete());
        }

        $file = $request->file('document');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $path = $file->store("customer_documents/{$user->id}", 'public');

        $doc = CustomerDocument::create([
            'user_id' => $user->id,
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'file_hash' => $fileHash,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'document_date' => $validated['document_date'] ?? null,
            'verified' => false,
        ]);

        CheckDocumentContentJob::dispatch($doc);

        $successMsg = ucfirst(str_replace('_', ' ', $validated['document_type'])).' uploaded successfully.';

        // Auto-analyse bank statements for affordability intelligence
        if ($validated['document_type'] === 'bank_statement'
            && in_array($file->getMimeType(), ['text/csv', 'text/plain'])
        ) {
            try {
                $this->bankAnalysis->analyseForUser($user->id);
                $successMsg .= ' Bank statement analysed — affordability updated.';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Bank statement analysis failed: '.$e->getMessage());
                $successMsg .= ' (Statement uploaded but analysis could not run — only CSV statements are analysed.)';
            }
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Return pre-qualification JSON — used by the live affordability widget.
     */
    public function preQualification()
    {
        $user = Auth::user();
        $result = $this->affordability->calculate($user);
        $status = $this->affordability->profileStatus($user);

        return response()->json([
            'eligible' => $result['eligible'],
            'disposable_income' => $result['disposable_income'],
            'max_instalment' => $result['max_instalment'],
            'profile_pct' => $status['percentage'],
            'missing' => $status['missing'],
        ]);
    }

    /**
     * Admin: verify a customer document.
     */
    public function verifyDocument(Request $request, CustomerDocument $document)
    {
        $request->validate([
            'verified' => 'required|boolean',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $document->update([
            'verified' => $request->verified,
            'verified_by' => Auth::id(),
            'verified_at' => $request->verified ? now() : null,
            'rejection_reason' => $request->verified ? null : $request->rejection_reason,
        ]);

        // The most common reason a CLO decision goes stale: it was made
        // when a required document wasn't verified yet. Refresh any of this
        // user's still-open applications now, rather than leaving a human
        // to notice and manually re-run it.
        LoanApplication::where('user_id', $document->user_id)
            ->whereIn('status', ['pending', 'under_review'])
            ->get()
            ->each(fn (LoanApplication $app) => app(CloDecisionEngine::class)->evaluate($app));

        return back()->with('success', 'Document '.($request->verified ? 'verified' : 'rejected').'.');
    }
}
