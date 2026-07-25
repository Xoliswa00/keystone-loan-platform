<?php

namespace App\Observers;

use App\Models\branches;
use App\Models\chart_of_accounts;
use App\Models\gl_accounts;

class charts_of_accounts
{
    /**
     * Handle the chart_of_accounts "created" event.
     */
    public function created(chart_of_accounts $chart_of_accounts): void
    {
        //

        $branches = branches::all();

        foreach ($branches as $branch) {
            $glCode = sprintf(
                '%03d-%s-%s',
                $chart_of_accounts->account_code,
                $branch->branch_code,
                $branch->location ?? '000'
            );

            gl_accounts::create([
                'chart_of_account_id' => $chart_of_accounts->id,
                'branch_id' => $branch->id,
                'full_account_no' => $glCode,
                'location_code' => $branch->location ?? '000',
            ]);
        }

    }

    /**
     * Handle the chart_of_accounts "updated" event.
     */
    public function updated(chart_of_accounts $chart_of_accounts): void
    {
        //
    }

    /**
     * Handle the chart_of_accounts "deleted" event.
     */
    public function deleted(chart_of_accounts $chart_of_accounts): void
    {
        //
    }

    /**
     * Handle the chart_of_accounts "restored" event.
     */
    public function restored(chart_of_accounts $chart_of_accounts): void
    {
        //
    }

    /**
     * Handle the chart_of_accounts "force deleted" event.
     */
    public function forceDeleted(chart_of_accounts $chart_of_accounts): void
    {
        //
    }
}
