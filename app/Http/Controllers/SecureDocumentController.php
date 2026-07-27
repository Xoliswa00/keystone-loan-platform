<?php

namespace App\Http\Controllers;

use App\Models\CustomerDocument;
use App\Models\FundingFacility;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Every PII/KYC document (ID copies, bank statements, payslips, proof of
 * payment, facility agreements) is stored on the private 'local' disk and
 * must be streamed through here — never linked directly via the 'public'
 * disk, which the webserver serves with zero authentication.
 */
class SecureDocumentController extends Controller
{
    private const APPLICATION_FIELDS = ['bank_statement', 'payslips', 'credit_score_report'];

    public function customerDocument(CustomerDocument $document)
    {
        $this->authoriseOwnerOrStaff($document->user_id);

        return $this->stream($document->file_path, $document->original_name);
    }

    public function applicationFile(LoanApplication $application, string $field)
    {
        abort_unless(in_array($field, self::APPLICATION_FIELDS, true), 404);

        $this->authoriseOwnerOrStaff($application->user_id);

        return $this->stream($application->{$field});
    }

    public function repaymentProof(LoanRepayment $repayment)
    {
        $this->authoriseOwnerOrStaff($repayment->user_id, ['admin', 'loan_officer', 'it_admin']);

        return $this->stream($repayment->proof_of_payment_path);
    }

    public function fundingAgreement(FundingFacility $fundingFacility)
    {
        abort_unless(Auth::user()->hasRole('admin', 'finance', 'it_admin'), 403);

        return $this->stream($fundingFacility->agreement_document_path);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function authoriseOwnerOrStaff(?int $ownerId, array $staffRoles = ['admin', 'loan_officer', 'finance', 'it_admin']): void
    {
        abort_unless(
            ($ownerId !== null && Auth::id() === $ownerId) || Auth::user()->hasRole(...$staffRoles),
            403
        );
    }

    private function stream(?string $path, ?string $downloadName = null)
    {
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        $headers = ['Content-Disposition' => 'inline'.($downloadName ? "; filename=\"{$downloadName}\"" : '')];

        return response()->file(Storage::disk('local')->path($path), $headers);
    }
}
