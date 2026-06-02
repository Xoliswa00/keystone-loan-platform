<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoanProduct;
use App\Models\CustomerProfile;

class AffordabilityService
{
    const AFFORDABILITY_RATIO = 0.30; // NCA: max 30% of disposable income as instalment

    const REQUIRED_DOCUMENTS = ['id_document', 'payslip', 'bank_statement'];

    // ──────────────────────────────────────────────
    // Core affordability calculation
    // ──────────────────────────────────────────────

    /**
     * Calculate full affordability breakdown from the user's profile.
     * Returns all figures needed for pre-qualification and audit.
     */
    public function calculate(User $user): array
    {
        $profile = $user->customerProfile;

        if (!$profile || !(float) $profile->net_monthly_income) {
            return [
                'eligible'          => false,
                'reason'            => 'Income details not on file. Please complete your profile.',
                'net_income'        => 0,
                'total_expenses'    => 0,
                'disposable_income' => 0,
                'max_instalment'    => 0,
            ];
        }

        $netIncome     = (float) $profile->net_monthly_income;
        $otherIncome   = (float) ($profile->other_income ?? 0);
        $totalIncome   = $netIncome + $otherIncome;

        $totalExpenses = (float) $profile->expense_housing
                       + (float) $profile->expense_transport
                       + (float) $profile->expense_existing_debt
                       + (float) $profile->expense_insurance
                       + (float) $profile->expense_living;

        $disposable    = $totalIncome - $totalExpenses;
        $maxInstalment = max(0, round($disposable * self::AFFORDABILITY_RATIO, 2));

        return [
            'eligible'          => $disposable > 0 && $maxInstalment > 0,
            'reason'            => $disposable <= 0 ? 'Disposable income is insufficient.' : null,
            'net_income'        => round($netIncome, 2),
            'other_income'      => round($otherIncome, 2),
            'total_income'      => round($totalIncome, 2),
            'expense_housing'   => round($profile->expense_housing, 2),
            'expense_transport' => round($profile->expense_transport, 2),
            'expense_debt'      => round($profile->expense_existing_debt, 2),
            'expense_insurance' => round($profile->expense_insurance, 2),
            'expense_living'    => round($profile->expense_living, 2),
            'total_expenses'    => round($totalExpenses, 2),
            'disposable_income' => round($disposable, 2),
            'max_instalment'    => $maxInstalment,
        ];
    }

    /**
     * Check whether a specific instalment amount passes the affordability test.
     */
    public function passes(User $user, float $instalmentAmount): bool
    {
        $result = $this->calculate($user);

        return $result['eligible'] && $instalmentAmount <= $result['max_instalment'];
    }

    /**
     * Maximum loan principal the user qualifies for on a given product and term.
     * Uses back-calculation from max instalment.
     */
    public function maxLoanAmount(LoanProduct $product, float $maxInstalment, int $months): float
    {
        $r = (float) $product->monthly_interest_rate;
        $serviceFee     = $product->serviceFeWithVat();
        $instalmentForPrincipal = $maxInstalment - $serviceFee;

        if ($instalmentForPrincipal <= 0) {
            return 0;
        }

        // Back-solve: instalment = (principal × (1 + r×months) + initiation_fee) / months
        // Approximate: ignore initiation fee in back-calc, then verify
        if ($r === 0.0 || $months === 1) {
            $principal = ($instalmentForPrincipal * $months) / (1 + $r * $months);
        } else {
            // Amortization PV formula: PV = PMT × [1 - (1+r)^-n] / r
            $principal = $instalmentForPrincipal * (1 - pow(1 + $r, -$months)) / $r;
        }

        // Clamp to product limits
        $principal = max($product->min_amount, min($product->max_amount, round($principal, 2)));

        return $principal;
    }

    // ──────────────────────────────────────────────
    // Profile completeness
    // ──────────────────────────────────────────────

    /**
     * Returns completeness percentage and list of missing items.
     * Used to gate loan applications and drive the dashboard progress card.
     */
    public function profileStatus(User $user): array
    {
        $checks = [
            'personal_details' => (bool) ($user->name && $user->email && $user->ID_Number),
            'income_details'   => (bool) ($user->customerProfile?->net_monthly_income > 0),
            'expense_details'  => (bool) ($user->customerProfile && (
                                    $user->customerProfile->expense_housing > 0 ||
                                    $user->customerProfile->expense_living  > 0
                                  )),
            'bank_account'     => $user->accountDetails()->exists(),
            'id_document'      => $user->customerDocuments()->where('document_type', 'id_document')->exists(),
            'payslip'          => $user->customerDocuments()->where('document_type', 'payslip')->exists(),
            'bank_statement'   => $user->customerDocuments()->where('document_type', 'bank_statement')->exists(),
        ];

        $completed = array_filter($checks);
        $total     = count($checks);
        $done      = count($completed);
        $pct       = (int) round(($done / $total) * 100);

        $missing = array_keys(array_filter($checks, fn($v) => !$v));

        return [
            'percentage' => $pct,
            'complete'   => $pct === 100,
            'checks'     => $checks,
            'missing'    => $missing,
        ];
    }

    /**
     * Hard gate: can this user submit a loan application?
     * Returns ['allowed' => bool, 'reason' => string|null]
     */
    public function canApply(User $user, LoanProduct $product): array
    {
        $status = $this->profileStatus($user);

        // Income and expense details are the minimum for affordability calc
        $minRequired = ['personal_details', 'income_details', 'expense_details', 'bank_account'];
        foreach ($minRequired as $key) {
            if (!$status['checks'][$key]) {
                return ['allowed' => false, 'reason' => "Profile incomplete: {$key}"];
            }
        }

        $affordability = $this->calculate($user);
        if (!$affordability['eligible']) {
            return ['allowed' => false, 'reason' => $affordability['reason']];
        }

        if ($product->requires_enhanced_affordability) {
            // Enhanced: must have employment tenure ≥ 6 months
            $tenure = $user->customerProfile?->employment_tenure;
            if (in_array($tenure, ['less_than_6m', null])) {
                return ['allowed' => false, 'reason' => 'Extended loans require at least 6 months of employment.'];
            }

            // Must have no active overdue loans
            $hasOverdue = \App\Models\RepaymentSchedule::where('user_id', $user->id)
                ->overdue()->exists();

            if ($hasOverdue) {
                return ['allowed' => false, 'reason' => 'You have overdue payments. Extended loans are not available.'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }
}
