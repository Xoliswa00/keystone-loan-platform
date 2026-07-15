<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CloDecision;
use App\Models\CustomerDocument;
use App\Models\LendingSetting;
use App\Models\LoanApplication;
use App\Models\PopiaConsent;
use App\Models\RepaymentSchedule;
use App\Models\User;

/**
 * CLO Decision Engine — advisory governance layer (see /CLO/*.md).
 *
 * Evaluates a loan application against the existing compliance/affordability
 * gates and produces a structured, auditable recommendation
 * (APPROVE|REJECT|ESCALATE|REVIEW). It never mutates LoanApplication/Loan
 * status itself — LoanController::approve()/reject() remain the sole
 * authority for execution, per the CLO Constitution's "CLO cannot execute
 * payments directly" and V1 "Learning Mode" (advisory only, no
 * auto-modification of production rules).
 */
class CloDecisionEngine
{
    public function __construct(
        protected CustomerLimitationService $limitations,
        protected AffordabilityService $affordability
    ) {}

    public function evaluate(LoanApplication $application): array
    {
        $user = $application->user;
        $product = $application->product;

        $fraudFlags = [];
        $requiredActions = [];
        $policyReferences = [];
        $forcedDecision = null; // set when a hard gate overrides the risk-score resolution
        $complianceStatus = 'PASS';
        $gateReason = null;

        // ── 1. NCR / NCA gates — reuse CustomerLimitationService, do not re-derive ──
        // Exclude this application itself: it already exists by the time the CLO
        // evaluates it (observer fires post-create), and would otherwise match its
        // own "pending application" gate.
        $gate = $this->limitations->canApply($user, $product, $application->id);

        if (! $gate['allowed']) {
            $complianceStatus = 'FAIL';
            $gateReason = $gate['reason'];
            $policyReferences[] = $gate['gate'];
            $requiredActions[] = $gate['reason'];

            $forcedDecision = in_array($gate['gate'], ['gate:blacklisted', 'gate:admin_restriction', 'gate:affordability'])
                ? 'REJECT'   // compliance/financial hard-stop
                : 'REVIEW';  // procedural: pending_application, rejection_cooldown, profile_incomplete
        }

        // ── 2. FICA-equivalent document completeness ──
        $profileStatus = $this->affordability->profileStatus($user);
        $missingDocs = array_intersect($profileStatus['missing'], ['id_document', 'payslip', 'bank_statement']);

        if (! empty($missingDocs)) {
            $fraudFlags[] = 'no_supporting_documents';
            $policyReferences[] = 'FICA';
            foreach ($missingDocs as $doc) {
                $requiredActions[] = "Upload missing document: {$doc}";
            }
            $forcedDecision ??= 'REVIEW';
        }

        // ── 3. Duplicate document reuse ──
        // The same source file (e.g. one bank statement) attached to two
        // *different* identities is near-conclusive of identity fraud, not
        // an oversight a re-upload can fix — so this forces REJECT outright
        // rather than the softer REVIEW used for a missing/incomplete doc.
        $duplicateDocs = $this->findDuplicateDocuments($user);

        if (! empty($duplicateDocs)) {
            $fraudFlags[] = 'duplicate_document_reuse';
            $policyReferences[] = 'Fraud: shared source document';
            $requiredActions[] = 'Manual fraud investigation required before any resubmission is considered.';
            $forcedDecision = 'REJECT';
        }

        // ── 4. Shared IP across different identities ──
        // Weaker signal than a byte-identical document (shared households,
        // NAT, mobile carriers all cause false positives), so this only
        // escalates to REVIEW and never downgrades an existing REJECT.
        if ($this->hasSharedIpWithOtherApplicant($application, $user)) {
            $fraudFlags[] = 'shared_ip_multiple_identities';
            $requiredActions[] = 'Verify applicant identity — submission IP matches another applicant\'s recent application.';
            $forcedDecision ??= 'REVIEW';
        }

        // ── 5. POPIA consent ──
        // Checks the CURRENT state (most recent row), not merely whether
        // consent was ever granted — a user can withdraw it later via
        // their profile, and a stale historical grant must not count.
        $hasConsent = PopiaConsent::isGranted($user->id, 'data_processing');

        if (! $hasConsent) {
            $policyReferences[] = 'POPIA consent';
            $requiredActions[] = 'Capture POPIA data-processing consent from applicant.';
            $forcedDecision ??= 'REVIEW';
        }

        // ── 6. Rule-based risk/fraud scoring (0-100) ──
        [$riskScore, $riskFlags, $riskPolicyRefs] = $this->scoreRisk($application, $user);
        $fraudFlags = array_merge($fraudFlags, $riskFlags);
        $policyReferences = array_merge($policyReferences, $riskPolicyRefs);

        // ── 7. Resolve final decision ──
        $settings = LendingSetting::current();
        $reviewThreshold = $settings->clo_risk_review_threshold;
        $escalateThreshold = $settings->clo_risk_escalate_threshold;

        $decision = $forcedDecision ?? match (true) {
            $riskScore >= $escalateThreshold => 'ESCALATE',
            $riskScore >= $reviewThreshold => 'REVIEW',
            default => 'APPROVE',
        };

        // A severe risk score escalates even a procedurally-forced REVIEW.
        if ($decision === 'REVIEW' && $riskScore >= $escalateThreshold) {
            $decision = 'ESCALATE';
        }

        $reason = $gateReason ?? ($fraudFlags
            ? 'Risk/compliance factors identified: '.implode('; ', array_unique($fraudFlags))
            : 'No compliance or risk concerns identified.');

        $result = [
            'decision' => $decision,
            'risk_score' => $riskScore,
            'compliance_status' => $complianceStatus,
            'fraud_flags' => array_values(array_unique($fraudFlags)),
            'policy_references' => array_values(array_unique(array_filter($policyReferences))),
            'reason' => $reason,
            'required_actions' => array_values(array_unique(array_filter($requiredActions))),
            'audit_required' => true,
        ];

        $this->record($application, $result);

        return $result;
    }

    /**
     * File-hash collisions between this applicant's KYC documents and any
     * other user's — catches the same bank statement/payslip/ID being reused
     * across fabricated identities, which filename/mime checks can't see.
     *
     * @return array<int,string> document types that were found duplicated
     */
    protected function findDuplicateDocuments(User $user): array
    {
        $hashes = CustomerDocument::where('user_id', $user->id)
            ->whereNotNull('file_hash')
            ->pluck('file_hash', 'document_type');

        if ($hashes->isEmpty()) {
            return [];
        }

        return CustomerDocument::whereIn('file_hash', $hashes->values())
            ->where('user_id', '!=', $user->id)
            ->pluck('document_type')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether this application's submission IP matches another applicant's
     * (different user) within the last 7 days — a softer signal than a
     * duplicate document, since NAT/shared households/mobile carriers can
     * share an IP innocently.
     */
    protected function hasSharedIpWithOtherApplicant(LoanApplication $application, User $user): bool
    {
        $ip = AuditLog::where('event', 'created')
            ->where('auditable_type', LoanApplication::class)
            ->where('auditable_id', $application->id)
            ->value('ip_address');

        if (! $ip) {
            return false;
        }

        // Join through loan_applications.user_id (the actual applicant) rather
        // than trusting audit_logs.user_id — that column records who performed
        // the action, which is usually the applicant for self-service
        // submissions but isn't guaranteed (e.g. staff-assisted applications).
        return LoanApplication::where('user_id', '!=', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('id', function ($query) use ($ip) {
                $query->select('auditable_id')
                    ->from('audit_logs')
                    ->where('event', 'created')
                    ->where('auditable_type', LoanApplication::class)
                    ->where('ip_address', $ip);
            })
            ->exists();
    }

    /**
     * @return array{0:int,1:array<int,string>,2:array<int,string>} [score, fraud_flags, policy_references]
     */
    protected function scoreRisk(LoanApplication $application, User $user): array
    {
        $score = 0;
        $flags = [];
        $refs = [];

        // Prior rejections — NCA s.81 reckless-credit context (re-lending soon after
        // a rejection elsewhere/here without re-assessment is high risk).
        $priorRejections = LoanApplication::where('user_id', $user->id)
            ->where('id', '<>', $application->id)
            ->where('status', 'rejected')
            ->count();

        if ($priorRejections > 0) {
            $score += min($priorRejections * 15, 30);
            $flags[] = 'recent_rejection';
            $refs[] = 'NCA s.81';
        }

        // Declared arrears on this application.
        if ($application->arrears) {
            $score += 20;
            $flags[] = 'declared_arrears';
        }

        // Existing overdue repayment schedules for this user.
        if (RepaymentSchedule::where('user_id', $user->id)->overdue()->exists()) {
            $score += 20;
            $flags[] = 'existing_overdue_payments';
        }

        // Requested instalment vs. max affordable instalment.
        $maxInstalment = (float) ($application->affordability_max_instalment ?? 0);
        $requested = (float) ($application->affordability_instalment_requested ?? 0);

        if ($maxInstalment > 0 && $requested > 0 && ($requested / $maxInstalment) > 0.9) {
            $score += 15;
            $flags[] = 'high_instalment_ratio';
        }

        // Employment tenure.
        $tenure = $user->customerProfile?->employment_tenure;
        if (in_array($tenure, ['less_than_6m', null], true)) {
            $score += 10;
            $flags[] = 'short_employment_tenure';
        }

        // Repeat-application velocity — more than one other application in the last 30 days.
        $recentApplications = LoanApplication::where('user_id', $user->id)
            ->where('id', '<>', $application->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentApplications > 1) {
            $score += 15;
            $flags[] = 'rapid_reapplication';
            $refs[] = 'NCA s.81';
        }

        // Requested amount near the product's ceiling.
        $product = $application->product;
        if ($product && $application->loan_amount >= ($product->max_amount * 0.9)) {
            $score += 10;
            $flags[] = 'requested_near_product_max';
        }

        // Clean repayment track record — offsets the purely punitive signals
        // above so a good repeat customer's 5th application isn't scored
        // identically to a stranger's 1st. Deliberately not added to $flags:
        // that array feeds fraud_flags, and this is the opposite of one.
        // Still just one input among several — never bypasses the
        // affordability/compliance gates in evaluate().
        $settledLoans = \App\Models\Loan::where('user_id', $user->id)
            ->where('status', 'settled')
            ->count();

        if ($settledLoans > 0) {
            $score -= min($settledLoans * 10, 20);
        }

        return [max(0, min($score, 100)), $flags, $refs];
    }

    protected function record(LoanApplication $application, array $result): void
    {
        CloDecision::create([
            'loan_application_id' => $application->id,
            'decision' => $result['decision'],
            'risk_score' => $result['risk_score'],
            'compliance_status' => $result['compliance_status'],
            'fraud_flags' => $result['fraud_flags'],
            'policy_references' => $result['policy_references'],
            'reason' => $result['reason'],
            'required_actions' => $result['required_actions'],
            'audit_required' => $result['audit_required'],
            'evaluated_at' => now(),
        ]);

        AuditLog::record('clo_decision', $application, [], $result, $result['reason']);
    }
}
