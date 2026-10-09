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

    private const INLINE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

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

        // Only PDFs and photos open in the browser. Anything else downloads, and
        // nosniff stops a browser treating a disguised upload as a page in the
        // viewer's session. The name comes from the client's original filename,
        // so it is reduced to safe characters before it goes into a header.
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
        $inline = in_array($mime, self::INLINE_TYPES, true);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $downloadName ?: basename($path));

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')."; filename=\"{$name}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
