<?php

namespace App\Observers;

use App\Models\branches;
use App\Models\chart_of_accounts;
use App\Models\gl_accounts;

class CreatedBranch
{
    /**
     * Handle the branches "created" event.
     */
    public function created(branches $branches): void
    {
        $chartAccounts = chart_of_accounts::all();

        foreach ($chartAccounts as $coa) {
            $glCode = sprintf(
                '%03d-%s-%s',
                $coa->account_code,
                $branches->branch_code,
                $branches->location ?? '000'
            );

            gl_accounts::create([
                'chart_of_account_id' => $coa->id,
                'branch_id' => $branches->id,
                'full_account_no' => $glCode,
                'location_code' => $branches->location ?? '000',
            ]);
        }
    }

    /**
     * Handle the branches "updated" event.
     */
    public function updated(branches $branches): void
    {
        //
    }

    /**
     * Handle the branches "deleted" event.
     */
    public function deleted(branches $branches): void
    {
        //
    }

    /**
     * Handle the branches "restored" event.
     */
    public function restored(branches $branches): void
    {
        //
    }

    /**
     * Handle the branches "force deleted" event.
     */
    public function forceDeleted(branches $branches): void
    {
        //
    }
}
