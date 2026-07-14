<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // These checked the legacy `role` column (values like 'owner',
        // 'approver') which no user actually has post-migration to
        // `system_role` — the gates always evaluated false. Real endpoint
        // security lives in the `role:` route middleware; these only gate
        // Blade nav visibility (@can('access-admin')).
        $staffRole = function ($user) {
            return $user->system_role ?? ($user->rule_id === 2 ? 'admin' : 'client');
        };

        Gate::define('access-admin', function ($user) use ($staffRole) {
            return in_array($staffRole($user), ['admin', 'finance', 'it_admin', 'loan_officer']);
        });

        Gate::define('approve-loans', function ($user) use ($staffRole) {
            return in_array($staffRole($user), ['admin', 'loan_officer']);
        });

        Gate::define('recon-finance', function ($user) use ($staffRole) {
            return in_array($staffRole($user), ['admin', 'finance']);
        });
    }
}
