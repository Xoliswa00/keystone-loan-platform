<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\branches;
use App\Models\chart_of_accounts;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Observers\charts_of_accounts;
use App\Observers\CreatedBranch;
use App\Observers\LoanObserver;
use App\Observers\LoanApplicationObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        branches::observe(CreatedBranch::class);
        chart_of_accounts::observe(charts_of_accounts::class);

        // Audit trail — every state change on Loan and LoanApplication is logged
        Loan::observe(LoanObserver::class);
        LoanApplication::observe(LoanApplicationObserver::class);
    }
}
