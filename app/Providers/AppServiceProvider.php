<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\branches;
use App\Models\chart_of_accounts;
use App\Observers\charts_of_accounts;
use App\Observers\CreatedBranch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    



    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
       branches::observe(CreatedBranch::class);
    chart_of_accounts::observe(charts_of_accounts::class);

    }
}
