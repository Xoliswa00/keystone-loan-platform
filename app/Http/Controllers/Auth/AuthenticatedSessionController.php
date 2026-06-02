<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * Intercepts admin/staff logins for 2FA verification.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // ── 2FA check for admin/staff roles ──────────────────────────────────
        $staffRoles = ['admin', 'finance', 'it_admin', 'loan_officer'];
        $role       = $user->system_role ?? ($user->rule_id === 2 ? 'admin' : 'client');

        if (in_array($role, $staffRoles) && $user->two_factor_enabled) {
            // Store user ID, log out, redirect to OTP page
            $userId = $user->id;
            Auth::logout();

            $request->session()->put('2fa_user_id', $userId);
            $request->session()->put('2fa_code_sent', false);
            $request->session()->save();

            return redirect()->route('two-factor.show');
        }

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
