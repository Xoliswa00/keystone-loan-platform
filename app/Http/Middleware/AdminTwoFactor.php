<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces 2FA for admin/finance/it_admin users.
 *
 * Flow:
 *  1. User logs in via standard Breeze flow
 *  2. This middleware intercepts authenticated admin users who haven't done 2FA yet
 *  3. Stores user ID in session, logs out Laravel auth
 *  4. Redirects to /auth/two-factor for OTP entry
 *  5. TwoFactorController verifies code and logs user back in
 */
class AdminTwoFactor
{
    const ADMIN_ROLES = ['admin', 'finance', 'it_admin', 'loan_officer'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Only enforce for staff roles
        $role = $user->system_role ?? ($user->rule_id === 2 ? 'admin' : 'client');

        if (! in_array($role, self::ADMIN_ROLES)) {
            return $next($request); // clients skip 2FA
        }

        // Check if 2FA is enabled for this user
        if (! $user->two_factor_enabled) {
            return $next($request); // 2FA not enabled — still allow (but show warning)
        }

        // Check if 2FA was completed recently (last 8 hours). TwoFactorController
        // stamps two_factor_verified_at on the user record, not a session key.
        $verifiedAt = $user->two_factor_verified_at;

        if ($verifiedAt && now()->diffInHours($verifiedAt) < 8) {
            return $next($request); // still within the 8-hour window
        }

        // Need 2FA — store user ID, log out, redirect
        if (! session('2fa_user_id')) {
            session(['2fa_user_id' => $user->id]);
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->save();

            return redirect()->route('two-factor.show');
        }

        return redirect()->route('two-factor.show');
    }
}
