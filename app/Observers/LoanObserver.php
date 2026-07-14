<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanStatusHistory;

class LoanObserver
{
    public function created(Loan $loan): void
    {
        AuditLog::record('created', $loan, [], $loan->toArray());
    }

    public function updating(Loan $loan): void
    {
        // Capture status change before save
        if ($loan->isDirty('status')) {
            $loan->statusChangeContext = [
                'from' => $loan->getOriginal('status'),
                'to' => $loan->status,
            ];
        }
    }

    public function updated(Loan $loan): void
    {
        $dirty = $loan->getDirty();
        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($loan->getOriginal(), $dirty);

        AuditLog::record('updated', $loan, $old, $dirty);

        // Log status change to history table
        if ($loan->statusChangeContext !== null) {
            LoanStatusHistory::create([
                'loan_id' => $loan->id,
                'loan_application_id' => $loan->loan_application_id,
                'from_status' => $loan->statusChangeContext['from'],
                'to_status' => $loan->statusChangeContext['to'],
                'changed_by' => auth()->id(),
                'ip_address' => request()->ip(),
                'reason' => request()->input('approval_comments')
                                       ?? request()->input('rejection_reason')
                                       ?? null,
            ]);
            $loan->statusChangeContext = null;
        }
    }
}
