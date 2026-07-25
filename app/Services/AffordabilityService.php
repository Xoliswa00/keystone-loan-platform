<?php

namespace App\Services;

use App\Models\LendingSetting;
use App\Models\LoanProduct;
use App\Models\User;

class AffordabilityService
{
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

        if (! $profile || ! (float) $profile->net_monthly_income) {
            return [
                'eligible' => false,
                'reason' => 'Income details not on file. Please complete your profile.',
                'net_income' => 0,
                'total_expenses' => 0,
                'disposable_income' => 0,
                'max_instalment' => 0,
            ];
        }

        $netIncome = (float) $profile->net_monthly_income;
        $otherIncome = (float) ($profile->other_income ?? 0);
        $totalIncome = $netIncome + $otherIncome;

        $totalExpenses = (float) $profile->expense_housing
                       + (float) $profile->expense_transport
                       + (float) $profile->expense_existing_debt
                       + (float) $profile->expense_insurance
                       + (float) $profile->expense_living;

        $disposable = $totalIncome - $totalExpenses;
        $maxInstalment = max(0, round($disposable * (float) LendingSetting::current()->affordability_ratio, 2));

        return [
            'eligible' => $disposable > 0 && $maxInstalment > 0,
            'reason' => $disposable <= 0 ? 'Disposable income is insufficient.' : null,
            'net_income' => round($netIncome, 2),
            'other_income' => round($otherIncome, 2),
            'total_income' => round($totalIncome, 2),
            'expense_housing' => round($profile->expense_housing, 2),
            'expense_transport' => round($profile->expense_transport, 2),
            'expense_debt' => round($profile->expense_existing_debt, 2),
            'expense_insurance' => round($profile->expense_insurance, 2),
            'expense_living' => round($profile->expense_living, 2),
            'total_expenses' => round($totalExpenses, 2),
            'disposable_income' => round($disposable, 2),
            'max_instalment' => $maxInstalment,
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
     * Maximum loan principal the user qualifies for on a given product and
     * term, i.e. the largest amount whose calculateRepayment() instalment
     * doesn't exceed $maxInstalment. Binary search rather than a closed-form
     * back-calculation — the initiation fee scales with principal, so there's
     * no clean algebraic inverse of calculateRepayment(); searching against
     * the real formula guarantees the amount returned actually passes
     * passes() if the client accepts it, instead of a formula that
     * approximates and can overshoot.
     *
     * Returns 0 if even the product's minimum amount isn't affordable.
     */
    public function maxLoanAmount(LoanProduct $product, float $maxInstalment, int $months): float
    {
        $low = (float) $product->min_amount;
        $high = (float) $product->max_amount;

        if ($product->calculateRepayment($low, $months)['base_instalment'] > $maxInstalment) {
            return 0;
        }

        if ($product->calculateRepayment($high, $months)['base_instalment'] <= $maxInstalment) {
            return $high;
        }

        // Narrow to the nearest rand — fees/interest don't vary meaningfully below that.
        while ($high - $low > 1) {
            $mid = round(($low + $high) / 2);

            if ($product->calculateRepayment($mid, $months)['base_instalment'] <= $maxInstalment) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }

    // ──────────────────────────────────────────────
    // Profile completeness
    // ──────────────────────────────────────────────

    /**
     * Returns completeness percentage and list of missing items.
     * Used to gate loan applications and drive the dashboard progress card.
     *
     * Document checks require verified=true, not just an upload — an
     * unreviewed document (nobody has clicked "Verify" on the admin side
     * yet) counts as missing, same as no document at all.
     */
    public function profileStatus(User $user): array
    {
        $checks = [
            'personal_details' => (bool) ($user->name && $user->email && $user->ID_Number),
            'income_details' => (bool) ($user->customerProfile?->net_monthly_income > 0),
            'expense_details' => (bool) ($user->customerProfile && (
                $user->customerProfile->expense_housing > 0 ||
                $user->customerProfile->expense_living > 0
            )),
            'bank_account' => $user->accountDetails()->exists(),
            'id_document' => $user->customerDocuments()->where('document_type', 'id_document')->verified()->exists(),
            'payslip' => $user->customerDocuments()->where('document_type', 'payslip')->verified()->exists(),
            'bank_statement' => $user->customerDocuments()->where('document_type', 'bank_statement')->verified()->exists(),
        ];

        $completed = array_filter($checks);
        $total = count($checks);
        $done = count($completed);
        $pct = (int) round(($done / $total) * 100);

        $missing = array_keys(array_filter($checks, fn ($v) => ! $v));

        return [
            'percentage' => $pct,
            'complete' => $pct === 100,
            'checks' => $checks,
            'missing' => $missing,
        ];
    }

    /**
     * Hard gate: can this user submit a loan application?
     * Returns ['allowed' => bool, 'reason' => string|null]
     */
    public function canApply(User $user, LoanProduct $product): array
    {
        $status = $this->profileStatus($user);

        // Only what makes an affordability calculation possible is a hard
        // pre-submission requirement.
        $minRequired = ['personal_details', 'income_details', 'expense_details', 'bank_account'];
        foreach ($minRequired as $key) {
            if (! $status['checks'][$key]) {
                return ['allowed' => false, 'reason' => "Profile incomplete: {$key}"];
            }
        }

        // KYC documents only need to be UPLOADED to submit, not yet staff-
        // verified — requiring verified=true here created a deadlock: a
        // first-time applicant has no LoanApplication yet (submission is
        // what creates one) and no Customer row yet (created only on a
        // successful submission — see ensureCustomerRecord()), so staff had
        // no page to find them on and nothing to click "Verify" against.
        // Verification is properly enforced afterward instead:
        // CloDecisionEngine::evaluate() already treats an unverified document
        // as "missing" and forces the application to REVIEW — that's where
        // staff verify it (Documents panel on admin.loan_applications.show)
        // before approving.
        foreach (['id_document', 'payslip', 'bank_statement'] as $docType) {
            if (! $user->customerDocuments()->where('document_type', $docType)->exists()) {
                return ['allowed' => false, 'reason' => "Profile incomplete: {$docType} not uploaded"];
            }
        }

        $affordability = $this->calculate($user);
        if (! $affordability['eligible']) {
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
