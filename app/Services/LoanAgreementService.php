<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NcaAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class LoanAgreementService
{
    const VAT_RATE = 0.15;

    // ──────────────────────────────────────────────────────────────────────────
    // Generate pre-agreement statement (NCA s.92)
    // Must be delivered BEFORE the agreement is signed
    // ──────────────────────────────────────────────────────────────────────────

    public function generatePreAgreement(LoanApplication $application, int $generatedBy): NcaAgreement
    {
        // The reference is deterministic (one per application), so a second
        // call must reuse the existing record rather than re-insert — the
        // caller (admin re-clicking, or download()'s regenerate-if-missing
        // fallback) shouldn't ever hit a duplicate-reference SQL error.
        $existing = NcaAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'pre_agreement_statement')
            ->first();

        if ($existing) {
            if (! Storage::disk('public')->exists($existing->file_path)) {
                $pdf = Pdf::loadView('agreements.pre_agreement', $this->buildAgreementData($application))
                    ->setPaper('A4', 'portrait')
                    ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);
                Storage::disk('public')->put($existing->file_path, $pdf->output());
            }

            return $existing;
        }

        $data = $this->buildAgreementData($application);
        $ref = 'KCP-PA-'.now()->format('Y').'-'.str_pad($application->id, 6, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('agreements.pre_agreement', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);

        $path = "agreements/{$application->user_id}/{$ref}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $agreement = NcaAgreement::create([
            'loan_application_id' => $application->id,
            'user_id' => $application->user_id,
            'reference' => $ref,
            'document_type' => 'pre_agreement_statement',
            'file_path' => $path,
            'generated_at' => now(),
            'principal_amount' => $data['principal'],
            'initiation_fee' => $data['initiationFeeExcl'],
            'initiation_fee_vat' => $data['initiationFeeVat'],
            'monthly_service_fee' => $data['serviceFeeExcl'],
            'monthly_service_fee_vat' => $data['serviceFeeVat'],
            'monthly_interest' => $data['monthlyInterest'],
            'total_cost_of_credit' => $data['totalCostOfCredit'],
            'annual_percentage_rate' => $data['apr'],
            'term_months' => $data['termMonths'],
            'first_payment_date' => $data['firstPaymentDateRaw'],
            'created_by' => $generatedBy,
        ]);

        // Stamp application
        $application->update([
            'nca_agreement_ref' => $ref,
            'nca_agreement_sent_at' => now(),
        ]);

        return $agreement;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generate full loan agreement (NCA s.93)
    // Generated after client accepts pre-agreement
    // ──────────────────────────────────────────────────────────────────────────

    public function generateLoanAgreement(LoanApplication $application, int $generatedBy): NcaAgreement
    {
        $existing = NcaAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'loan_agreement')
            ->first();

        if ($existing) {
            if (! Storage::disk('public')->exists($existing->file_path)) {
                $pdf = Pdf::loadView('agreements.loan_agreement', $this->buildAgreementData($application))
                    ->setPaper('A4', 'portrait')
                    ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);
                Storage::disk('public')->put($existing->file_path, $pdf->output());
            }

            return $existing;
        }

        $data = $this->buildAgreementData($application);
        $ref = 'KCP-LA-'.now()->format('Y').'-'.str_pad($application->id, 6, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('agreements.loan_agreement', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);

        $path = "agreements/{$application->user_id}/{$ref}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $agreement = NcaAgreement::create([
            'loan_application_id' => $application->id,
            'user_id' => $application->user_id,
            'reference' => $ref,
            'document_type' => 'loan_agreement',
            'file_path' => $path,
            'generated_at' => now(),
            'principal_amount' => $data['principal'],
            'initiation_fee' => $data['initiationFeeExcl'],
            'initiation_fee_vat' => $data['initiationFeeVat'],
            'monthly_service_fee' => $data['serviceFeeExcl'],
            'monthly_service_fee_vat' => $data['serviceFeeVat'],
            'monthly_interest' => $data['monthlyInterest'],
            'total_cost_of_credit' => $data['totalCostOfCredit'],
            'annual_percentage_rate' => $data['apr'],
            'term_months' => $data['termMonths'],
            'first_payment_date' => $data['firstPaymentDateRaw'],
            'created_by' => $generatedBy,
        ]);

        return $agreement;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Record client acceptance (digital signature / SMS OTP confirmation)
    // ──────────────────────────────────────────────────────────────────────────

    public function recordAcceptance(NcaAgreement $agreement, string $method = 'digital'): void
    {
        $agreement->update([
            'accepted_at' => now(),
            'acceptance_method' => $method,
        ]);

        $application = $agreement->loanApplication;
        if ($application) {
            $application->update([
                'nca_agreement_accepted_at' => now(),
                'cooling_off_expires_at' => $this->addBusinessDays(now(), 5),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generate settlement quote (NCA s.125)
    // ──────────────────────────────────────────────────────────────────────────

    public function generateSettlementQuote(\App\Models\Loan $loan, int $generatedBy): NcaAgreement
    {
        $application = $loan->loanApplication;
        $outstanding = (float) $loan->remaining_balance;
        $quoteDate = now();
        $quoteExpires = $quoteDate->copy()->addDays(5); // settlement quote valid 5 days

        // NCA s.125 rebate: remaining interest and fees not yet earned
        $interestRebate = (float) $loan->deferred_interest;
        $feeRebate = (float) $loan->deferred_fees;
        $settlementAmt = max(0, $outstanding - $interestRebate - $feeRebate);

        // Unlike the pre-agreement/loan-agreement (one per application, so
        // those are made idempotent instead), a settlement quote is meant to
        // be regenerated as the balance changes — so its reference needs a
        // sequence suffix rather than being purely deterministic on loan ID,
        // or a second quote the same year collides on the unique constraint.
        $sequence = NcaAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'settlement_quote')
            ->count() + 1;
        $ref = 'KCP-SQ-'.now()->format('Y').'-'.str_pad($loan->id, 6, '0', STR_PAD_LEFT).'-'.str_pad($sequence, 2, '0', STR_PAD_LEFT);

        $data = array_merge($this->buildAgreementData($application), [
            'outstanding' => $outstanding,
            'interestRebate' => $interestRebate,
            'feeRebate' => $feeRebate,
            'settlementAmt' => $settlementAmt,
            'quoteDate' => $quoteDate->format('d F Y'),
            'quoteExpires' => $quoteExpires->format('d F Y'),
        ]);

        $pdf = Pdf::loadView('agreements.settlement_quote', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true]);

        $path = "agreements/{$loan->user_id}/{$ref}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        // Store in early_settlements as well
        \App\Models\EarlySettlement::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'settlement_date' => $quoteDate->toDateString(),
            'outstanding_balance' => $outstanding,
            'interest_rebate' => $interestRebate,
            'fee_rebate' => $feeRebate,
            'settlement_amount' => $settlementAmt,
            'status' => 'quoted',
            'quote_expires_at' => $quoteExpires,
            'processed_by' => $generatedBy,
        ]);

        return NcaAgreement::create([
            'loan_application_id' => $application->id,
            'user_id' => $loan->user_id,
            'reference' => $ref,
            'document_type' => 'settlement_quote',
            'file_path' => $path,
            'generated_at' => now(),
            'principal_amount' => $data['principal'],
            'total_cost_of_credit' => $settlementAmt,
            'term_months' => $data['termMonths'],
            'created_by' => $generatedBy,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Build all data needed by the agreement templates
    // ──────────────────────────────────────────────────────────────────────────

    public function buildAgreementData(LoanApplication $application): array
    {
        $user = $application->user;
        $product = $application->product ?? LoanProduct::where('code', 'standard')->first();
        $fee = $application->loanfee;
        $schedule = $application->repaymentSchedules()->orderBy('installment_number')->get();

        $principal = (float) $application->loan_amount;
        $termMonths = $application->loan_term_months ?? 1;
        $monthlyRate = $product ? (float) $product->monthly_interest_rate : 0.05;

        // Fee breakdown (excl. and incl. VAT)
        $initiationFeeExcl = $fee ? (float) $fee->initiation_fee / (1 + self::VAT_RATE) : 0;
        $initiationFeeVat = $fee ? (float) $fee->initiation_fee - $initiationFeeExcl : 0;

        $serviceFeeMonthlyExcl = $fee ? ((float) $fee->service_fee / $termMonths) / (1 + self::VAT_RATE) : 0;
        $serviceFeeMonthlyVat = $fee ? (($serviceFeeMonthlyExcl) * self::VAT_RATE) : 0;

        $totalServiceExcl = $serviceFeeMonthlyExcl * $termMonths;
        $totalServiceVat = $serviceFeeMonthlyVat * $termMonths;

        $monthlyInterest = round($principal * $monthlyRate, 2);
        $totalInterest = $fee ? (float) $fee->interest_amount : round($monthlyInterest * $termMonths, 2);

        $totalCostOfCredit = $principal + $totalInterest + ($fee ? (float) $fee->initiation_fee : 0) + ($fee ? (float) $fee->service_fee : 0);
        $instalmentAmount = $schedule->first()?->emi_amount ?? round($totalCostOfCredit / $termMonths, 2);

        // APR calculation (NCA s.102 — must disclose)
        // For short-term: APR = monthly_rate × 12
        $apr = round($monthlyRate * 12 * 100, 2);

        $firstPaymentDate = $schedule->first()?->due_date;
        $lastPaymentDate = $schedule->last()?->due_date;

        // NCA credit type classification
        $ncaCreditType = $this->classifyNcaCreditType($principal, $termMonths);

        // Company / credit provider details — loaded from the Company settings table
        $company = \App\Models\Company::settings();

        return [
            // Credit provider — all from Company settings table
            'companyName' => $company?->name ?? config('app.name'),
            'companyAddress' => $company?->getFormattedAddress() ?? $company?->physical_address ?? $company?->address ?? 'South Africa',
            'ncr_number' => $company?->ncr_number ?? '—',
            'vat_number' => $company?->vat_number ?? '—',
            'company_reg' => $company?->registration_no ?? '—',
            'contact_phone' => $company?->phone ?? '',
            'contact_email' => $company?->email ?? '',
            'authorised_signatory' => $company?->authorised_signatory ?? 'Authorised Representative',
            'signatory_title' => $company?->signatory_title ?? 'Director',
            'tagline' => $company?->tagline ?? 'Capital. Partnership. Growth.',

            // Consumer
            'clientName' => $user->name,
            'clientId' => $user->ID_Number,
            'clientDob' => $user->date_of_birth
                                    ? Carbon::parse($user->date_of_birth)->format('d F Y')
                                    : '—',
            'clientAddress' => $user->address,
            'clientPhone' => $user->phone,
            'clientEmail' => $user->email,
            'customerCode' => $user->customer?->customer_code ?? '—',

            // Loan terms
            'applicationRef' => str_pad($application->id, 6, '0', STR_PAD_LEFT),
            'loanType' => ucfirst($application->loan_type),
            'ncaCreditType' => $ncaCreditType,
            'principal' => $principal,
            'termMonths' => $termMonths,
            'firstPaymentDate' => $firstPaymentDate
                                    ? Carbon::parse($firstPaymentDate)->format('d F Y')
                                    : '—',
            // Raw (unformatted, possibly null) value — for writing to
            // NcaAgreement.first_payment_date, which is cast as a real date
            // column. The display string above can't be reused there: it's
            // either a formatted date Carbon happens to parse back OK, or
            // the '—' placeholder, which blows up the date cast.
            'firstPaymentDateRaw' => $firstPaymentDate,
            'lastPaymentDate' => $lastPaymentDate
                                    ? Carbon::parse($lastPaymentDate)->format('d F Y')
                                    : '—',

            // Interest
            'monthlyRatePct' => round($monthlyRate * 100, 2),
            'annualRatePct' => $apr,
            'apr' => $apr,
            'monthlyInterest' => $monthlyInterest,
            'totalInterest' => $totalInterest,

            // Fees (NCA s.102 — all must be disclosed separately, excl. and incl. VAT)
            'initiationFeeExcl' => round($initiationFeeExcl, 2),
            'initiationFeeVat' => round($initiationFeeVat, 2),
            'initiationFeeIncl' => round($initiationFeeExcl + $initiationFeeVat, 2),
            'serviceFeeExcl' => round($serviceFeeMonthlyExcl, 2),
            'serviceFeeVat' => round($serviceFeeMonthlyVat, 2),
            'serviceFeeIncl' => round($serviceFeeMonthlyExcl + $serviceFeeMonthlyVat, 2),
            'totalServiceFeeExcl' => round($totalServiceExcl, 2),
            'totalServiceFeeVat' => round($totalServiceVat, 2),

            // Totals
            'instalmentAmount' => $instalmentAmount,
            'totalCostOfCredit' => round($totalCostOfCredit, 2),
            'totalAmountPayable' => round($totalCostOfCredit, 2),

            // Schedule
            'schedule' => $schedule,

            // Meta
            'generatedAt' => now()->format('d F Y H:i'),
            'today' => now()->format('d F Y'),

            // NCA rights
            'coolingOffDays' => 5,
            'coolingOffExpiry' => $firstPaymentDate
                ? Carbon::parse($firstPaymentDate)->subDays(2)->format('d F Y')
                : $this->addBusinessDays(now(), 5)->format('d F Y'),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function classifyNcaCreditType(float $principal, int $months): string
    {
        // NCA categories for unsecured credit
        if ($principal <= 8000 && $months <= 6) {
            return 'Short-term Credit Agreement';
        }
        if ($principal <= 15000) {
            return 'Small Credit Agreement';
        }
        if ($principal <= 250000) {
            return 'Intermediate Credit Agreement';
        }

        return 'Large Credit Agreement';
    }

    protected function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $saHolidays = ['01-01', '03-21', '04-27', '05-01', '06-16', '08-09', '09-24', '12-16', '12-25', '12-26'];
        $count = 0;
        $current = $date->copy();

        while ($count < $days) {
            $current->addDay();
            if (! $current->isWeekend() && ! in_array($current->format('m-d'), $saHolidays)) {
                $count++;
            }
        }

        return $current;
    }
}
