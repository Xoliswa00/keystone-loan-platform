<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Services\CloDecisionEngine;

class LoanApplicationObserver
{
    public function created(LoanApplication $application): void
    {
        AuditLog::record('created', $application, [], [
            'loan_amount' => $application->loan_amount,
            'loan_type' => $application->loan_type,
            'status' => $application->status,
            'user_id' => $application->user_id,
        ]);

        // LoanSubmitted event → CLO evaluation (advisory only, see /CLO/*.md)
        app(CloDecisionEngine::class)->evaluate($application);
    }

    public function updated(LoanApplication $application): void
    {
        $dirty = $application->getDirty();

        $significant = array_diff_key($dirty, array_flip(['updated_at']));
        if (empty($significant)) {
            return;
        }

        $old = array_intersect_key($application->getOriginal(), $significant);

        AuditLog::record('updated', $application, $old, $significant);

        // Stamp last_rejected_at on user when application is rejected.
        // forceFill(), not update() — last_rejected_at isn't in
        // User::$fillable, so update() silently no-ops. It has a query
        // fallback in CustomerLimitationService::checkRejectionCooldown()
        // (queries loan_applications directly when this is unset), which is
        // why the NCR cooldown gate still worked despite this — but this
        // stamp is what's supposed to be the fast path.
        if (isset($dirty['status']) && $dirty['status'] === 'rejected') {
            $application->user?->forceFill(['last_rejected_at' => now()])->save();
        }
    }
}
