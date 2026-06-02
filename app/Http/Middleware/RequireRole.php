<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access middleware.
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,loan_officer')   // allow multiple roles
 *   ->middleware('role:admin,finance')
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $userRole = $user->system_role ?? ($user->rule_id === 2 ? 'admin' : 'client');

        // 'admin' always passes — they have full access
        if ($userRole === 'admin' || in_array($userRole, $roles)) {
            return $next($request);
        }

        abort(403, "You don't have permission to access this area.");
    }
}
