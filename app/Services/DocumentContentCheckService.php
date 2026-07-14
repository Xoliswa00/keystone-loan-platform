<?php

namespace App\Services;

use App\Models\CustomerDocument;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Cheap, automated pre-check for uploaded KYC documents — a signal for the
 * admin doing the manual Verify review, never a replacement for it. Only
 * handles PDFs with a real text layer (most bank/employer-issued PDFs have
 * one); image uploads are marked not_applicable since that needs OCR, which
 * this phase deliberately doesn't attempt (unreliable for ID photos, needs
 * a Tesseract system dependency).
 */
class DocumentContentCheckService
{
    /**
     * N of these keywords must appear (case-insensitive) for a document to
     * be considered plausible for its claimed type.
     */
    const KEYWORDS = [
        'bank_statement' => [
            'FNB', 'FIRST NATIONAL BANK', 'ABSA', 'STANDARD BANK', 'NEDBANK', 'CAPITEC',
            'AFRICAN BANK', 'TYMEBANK', 'BIDVEST BANK', 'DISCOVERY BANK', 'INVESTEC',
            'OLD MUTUAL',
            'STATEMENT', 'ACCOUNT NUMBER', 'OPENING BALANCE', 'CLOSING BALANCE',
            'AVAILABLE BALANCE', 'BRANCH CODE',
        ],
        'payslip' => [
            'PAYSLIP', 'PAY SLIP', 'GROSS PAY', 'GROSS SALARY', 'NET PAY', 'NETT PAY',
            'PAYE', 'UIF', 'TAX NUMBER', 'EMPLOYEE NUMBER', 'DEDUCTIONS',
        ],
        'id_document' => [
            'REPUBLIC OF SOUTH AFRICA', 'IDENTITY DOCUMENT', 'SMART ID CARD',
        ],
        'proof_of_address' => [
            'MUNICIPALITY', 'RATES AND TAXES', 'ELECTRICITY', 'WATER ACCOUNT', 'ERF NO',
            'ACCOUNT NUMBER', 'STATEMENT',
        ],
    ];

    const MIN_HITS = [
        'bank_statement' => 2,
        'payslip' => 2,
        'id_document' => 1,
        'proof_of_address' => 2,
    ];

    public function check(CustomerDocument $document): void
    {
        if (! array_key_exists($document->document_type, self::KEYWORDS)) {
            $document->update(['content_check_status' => 'not_applicable']);

            return;
        }

        $path = Storage::disk('public')->path($document->file_path);

        if (! file_exists($path)) {
            $this->markInconclusive($document, 'File not found on disk.');

            return;
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            $document->update([
                'content_check_status' => 'not_applicable',
                'content_check_notes' => 'Image upload — automated content check needs OCR, not enabled yet.',
            ]);

            return;
        }

        try {
            $text = strtoupper((new Parser)->parseFile($path)->getText());
        } catch (\Exception $e) {
            $this->markInconclusive($document, 'Could not read PDF content: '.substr($e->getMessage(), 0, 150));

            return;
        }

        if (trim($text) === '') {
            $this->markInconclusive($document, 'PDF has no extractable text — likely a scanned image with no text layer.');

            return;
        }

        $hits = collect(self::KEYWORDS[$document->document_type])
            ->filter(fn ($keyword) => str_contains($text, $keyword));

        // Strongest single signal for an ID document: does it contain the
        // applicant's own declared ID number?
        if ($document->document_type === 'id_document' && $document->user?->ID_Number
            && str_contains($text, $document->user->ID_Number)) {
            $hits->push('matches applicant ID number');
        }

        $minHits = self::MIN_HITS[$document->document_type];

        if ($hits->count() >= $minHits) {
            $document->update([
                'content_check_status' => 'plausible',
                'content_check_notes' => 'Matched: '.$hits->implode(', '),
            ]);
        } else {
            $this->markInconclusive(
                $document,
                "Only matched {$hits->count()}/{$minHits} expected terms for this document type — worth a closer look."
            );
        }
    }

    private function markInconclusive(CustomerDocument $document, string $note): void
    {
        $document->update([
            'content_check_status' => 'inconclusive',
            'content_check_notes' => $note,
        ]);
    }
}
