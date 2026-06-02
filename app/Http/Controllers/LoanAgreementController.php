<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Loan;
use App\Models\NcaAgreement;
use App\Services\LoanAgreementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LoanAgreementController extends Controller
{
    protected LoanAgreementService $agreements;

    public function __construct(LoanAgreementService $agreements)
    {
        $this->agreements = $agreements;
    }

    // ── List all agreements for an application ────────────────────────────────

    public function index(LoanApplication $application)
    {
        $this->authorise($application);

        $agreements = NcaAgreement::where('loan_application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('agreements.index', compact('application', 'agreements'));
    }

    // ── Generate pre-agreement statement (admin action) ────────────────────

    public function generatePreAgreement(LoanApplication $application)
    {
        $agreement = $this->agreements->generatePreAgreement($application, Auth::id());

        return redirect()->back()
            ->with('success', "Pre-agreement statement generated. Reference: {$agreement->reference}");
    }

    // ── Generate full loan agreement (admin action, after pre-agreement) ───

    public function generateLoanAgreement(LoanApplication $application)
    {
        if (!$application->nca_agreement_sent_at) {
            return redirect()->back()
                ->with('error', 'Pre-agreement statement must be sent to the client before generating the loan agreement.');
        }

        $agreement = $this->agreements->generateLoanAgreement($application, Auth::id());

        return redirect()->back()
            ->with('success', "Loan agreement generated. Reference: {$agreement->reference}");
    }

    // ── Generate settlement quote ─────────────────────────────────────────

    public function generateSettlementQuote(Loan $loan)
    {
        $agreement = $this->agreements->generateSettlementQuote($loan, Auth::id());

        return redirect()->back()
            ->with('success', "Settlement quote generated. Reference: {$agreement->reference}");
    }

    // ── Download / stream PDF ─────────────────────────────────────────────

    public function download(NcaAgreement $agreement)
    {
        // Clients can only access their own documents
        if (Auth::user()->rule_id !== 2 && $agreement->user_id !== Auth::id()) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($agreement->file_path)) {
            // Regenerate on the fly if file missing
            $application = $agreement->loanApplication;
            if ($agreement->document_type === 'loan_agreement') {
                $this->agreements->generateLoanAgreement($application, Auth::id());
            } else {
                $this->agreements->generatePreAgreement($application, Auth::id());
            }
            $agreement->refresh();
        }

        $filename = $agreement->reference . '.pdf';

        return response()->file(
            Storage::disk('public')->path($agreement->file_path),
            ['Content-Disposition' => "inline; filename=\"{$filename}\""]
        );
    }

    // ── Record client digital acceptance ─────────────────────────────────

    public function accept(Request $request, NcaAgreement $agreement)
    {
        if ($agreement->user_id !== Auth::id()) {
            abort(403);
        }

        if ($agreement->isSigned()) {
            return redirect()->back()->with('error', 'Agreement already accepted.');
        }

        $this->agreements->recordAcceptance($agreement, 'digital');

        return redirect()->back()
            ->with('success', 'Agreement accepted. Your loan is being processed.');
    }

    // ── Preview (HTML) for admin review ──────────────────────────────────

    public function preview(LoanApplication $application, string $type = 'pre_agreement_statement')
    {
        $data = $this->agreements->buildAgreementData($application);
        $view = match($type) {
            'loan_agreement'   => 'agreements.loan_agreement',
            'settlement_quote' => 'agreements.settlement_quote',
            default            => 'agreements.pre_agreement',
        };

        return view($view, $data);
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function authorise(LoanApplication $application): void
    {
        if (Auth::user()->rule_id !== 2 && $application->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
