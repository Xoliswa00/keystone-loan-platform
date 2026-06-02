<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        Loan::observe(LoanObserver::class);
        LoanApplication::observe(LoanApplicationObserver::class);

        // ── Password policy ────────────────────────────────────────────────────
        // Min 8 chars, at least 1 letter + 1 number. Applied everywhere Rules\Password::defaults() is used.
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->numbers()
                ->uncompromised();
        });

        // ── Rate limiters ──────────────────────────────────────────────────────

        // Login: 3 wrong attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Loan application: 3 submissions per day per authenticated user
        RateLimiter::for('loan_application', function (Request $request) {
            return Limit::perDay(3)->by($request->user()?->id ?? $request->ip());
        });

        // File uploads: 10 per hour per user
        RateLimiter::for('file_upload', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?? $request->ip());
        });

        // Nu-Pay import: 5 per hour (large processing jobs)
        RateLimiter::for('nupay_import', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?? $request->ip());
        });
    }
}
