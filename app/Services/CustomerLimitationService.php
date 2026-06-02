<?php

namespace App\Services;

use App\Models\{User, Loan, LoanApplication, LoanProduct};
use Carbon\Carbon;

/**
 * Customer Limitation Service — NCR compliance gates
 *
 * Checks all restrictions before a client can submit a loan application.
 * Returns a structured result so the UI can show exactly why and when.
 *
 * Gates (in priority order):
 *  1. Admin restriction (manual block by staff)
 *  2. Blacklisted (own internal list)
 *  3. Active undisbursed/disbursed loan
 *  4. Pending/under-review application
 *  5. Post-rejection NCR waiting period (default 30 days)
 *  6. Profile incomplete (income required for affordability)
 *  7. Affordability — disposable income ≤ 0
 *  8. Enhanced check for extended products
 *
 * NCR Note:
 *  The NCA does not prescribe a fixed waiting period after rejection.
 *  However, Section 81 (reckless credit) means lending to someone who
 *  was just rejected elsewhere is high risk. 30 days is industry standard
 *  and configurable via config('lending.rejection_cooldown_days').
 */
class CustomerLimitationService
{
    const REJECTION_COOLDOWN_DAYS = 30; // configurable

    public function __construct(
        protected AffordabilityService $affordability
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Main gate — call before showing the application form and on store()
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{allowed:bool, reason:string|null, unblock_at:string|null, gate:string|null}
     */
    public function canApply(User $user, ?LoanProduct $product = null): array
    {
        // 1. Admin restriction
        if ($user->applications_restricted) {
            $expiresAt = $user->restriction_expires_at;

            // Auto-lift if expired
            if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
                $user->update(['applications_restricted' => false, 'restriction_reason' => null]);
            } else {
                return $this->block(
                    'gate:admin_restriction',
                    'Your account has been restricted from making new applications. ' .
                    ($user->restriction_reason ? 'Reason: ' . $user->restriction_reason . '. ' : '') .
                    'Please contact us for assistance.',
                    $expiresAt ? Carbon::parse($expiresAt)->toDateString() : null
                );
            }
        }

        // 2. Blacklisted (internal)
        if ($user->blacklisted) {
            return $this->block(
                'gate:blacklisted',
                'Your account is not eligible for new credit facilities at this time. Please contact support.'
            );
        }

        // 3. Active loan (not settled/rejected/archived/written_off)
        $activeLoan = Loan::where('user_id', $user->id)
            ->whereNotIn('status', ['settled', 'rejected', 'archived', 'written_off'])
            ->first();

        if ($activeLoan) {
            $loanRef = '#' . str_pad($activeLoan->id, 6, '0', STR_PAD_LEFT);
            return $this->block(
                'gate:active_loan',
                "You have an active loan {$loanRef} that must be fully settled before a new application can be submitted.",
                null
            );
        }

        // 4. Pending/under-review application
        $pendingApp = LoanApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->first();

        if ($pendingApp) {
            $appRef = '#' . str_pad($pendingApp->id, 6, '0', STR_PAD_LEFT);
            return $this->block(
                'gate:pending_application',
                "You have an application {$appRef} currently under review. Please wait for a decision before submitting a new one."
            );
        }

        // 5. Post-rejection NCR waiting period
        $cooldownResult = $this->checkRejectionCooldown($user);
        if (!$cooldownResult['allowed']) {
            return $cooldownResult;
        }

        // 6. Profile completeness (income is minimum required for affordability)
        $profile = $user->customerProfile;
        if (!$profile || !(float)($profile->net_monthly_income ?? 0)) {
            return $this->block(
                'gate:profile_incomplete',
                'Your financial profile is incomplete. Please add your income details to complete an affordability assessment before applying.',
                null,
                route('customer-profile.show')
            );
        }

        // 7. Affordability
        $affordResult = $this->affordability->calculate($user);
        if (!$affordResult['eligible']) {
            return $this->block(
                'gate:affordability',
                'Based on your declared income and expenses, you do not currently qualify for a loan. ' .
                ($affordResult['reason'] ?? 'Your disposable income is insufficient.')
            );
        }

        // 8. Enhanced affordability for extended products
        if ($product?->requires_enhanced_affordability) {
            $canApply = $this->affordability->canApply($user, $product);
            if (!$canApply['allowed']) {
                return $this->block('gate:enhanced_affordability', $canApply['reason']);
            }
        }

        return ['allowed' => true, 'reason' => null, 'unblock_at' => null, 'gate' => null, 'action_url' => null];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NCR rejection cooldown check
    // ──────────────────────────────────────────────────────────────────────────

    public function checkRejectionCooldown(User $user): array
    {
        $cooldownDays = config('lending.rejection_cooldown_days', self::REJECTION_COOLDOWN_DAYS);

        // Use stored last_rejected_at if available (updated by observer)
        $lastRejected = $user->last_rejected_at
            ? Carbon::parse($user->last_rejected_at)
            : null;

        // Fallback: query the table
        if (!$lastRejected) {
            $lastRejectionDate = LoanApplication::where('user_id', $user->id)
                ->where('status', 'rejected')
                ->max('approval_date');

            $lastRejected = $lastRejectionDate ? Carbon::parse($lastRejectionDate) : null;
        }

        if (!$lastRejected) {
            return ['allowed' => true, 'reason' => null, 'unblock_at' => null, 'gate' => null];
        }

        $unblockAt = $lastRejected->copy()->addDays($cooldownDays);

        if ($unblockAt->isFuture()) {
            return $this->block(
                'gate:rejection_cooldown',
                "Your last application was unsuccessful. Under our lending policy (aligned with NCR guidelines), " .
                "you may reapply after a {$cooldownDays}-day waiting period.",
                $unblockAt->toDateString()
            );
        }

        return ['allowed' => true, 'reason' => null, 'unblock_at' => null, 'gate' => null];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Get all restrictions for display on the dashboard
    // ──────────────────────────────────────────────────────────────────────────

    public function getRestrictions(User $user): array
    {
        $result = $this->canApply($user);

        if ($result['allowed']) {
            return [];
        }

        return [$result];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admin: manually restrict a user
    // ──────────────────────────────────────────────────────────────────────────

    public function restrict(User $user, string $reason, ?Carbon $expiresAt = null, int $adminId = null): void
    {
        $user->update([
            'applications_restricted' => true,
            'restriction_reason'      => $reason,
            'restriction_expires_at'  => $expiresAt,
            'restricted_by'           => $adminId,
        ]);
    }

    public function lift(User $user): void
    {
        $user->update([
            'applications_restricted' => false,
            'restriction_reason'      => null,
            'restriction_expires_at'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function block(string $gate, string $reason, ?string $unblockAt = null, ?string $actionUrl = null): array
    {
        return [
            'allowed'    => false,
            'gate'       => $gate,
            'reason'     => $reason,
            'unblock_at' => $unblockAt,
            'action_url' => $actionUrl,
        ];
    }
}
