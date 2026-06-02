<?php

namespace App\Observers;

use App\Models\LoanApplication;
use App\Models\AuditLog;

class LoanApplicationObserver
{
    public function created(LoanApplication $application): void
    {
        AuditLog::record('created', $application, [], [
            'loan_amount' => $application->loan_amount,
            'loan_type'   => $application->loan_type,
            'status'      => $application->status,
            'user_id'     => $application->user_id,
        ]);
    }

    public function updated(LoanApplication $application): void
    {
        $dirty = $application->getDirty();

        // Don't log trivial timestamp-only updates
        $significant = array_diff_key($dirty, array_flip(['updated_at']));
        if (empty($significant)) {
            return;
        }

        $old = array_intersect_key($application->getOriginal(), $significant);

        AuditLog::record('updated', $application, $old, $significant);
    }
}
