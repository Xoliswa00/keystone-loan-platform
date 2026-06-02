<?php

namespace App\Observers;

use App\Models\Loan;
use App\Models\AuditLog;
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
            $loan->_statusChanging = [
                'from' => $loan->getOriginal('status'),
                'to'   => $loan->status,
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
        if (isset($loan->_statusChanging)) {
            LoanStatusHistory::create([
                'loan_id'              => $loan->id,
                'loan_application_id'  => $loan->loan_application_id,
                'from_status'          => $loan->_statusChanging['from'],
                'to_status'            => $loan->_statusChanging['to'],
                'changed_by'           => auth()->id(),
                'ip_address'           => request()->ip(),
                'reason'               => request()->input('approval_comments')
                                       ?? request()->input('rejection_reason')
                                       ?? null,
            ]);
            unset($loan->_statusChanging);
        }
    }
}
