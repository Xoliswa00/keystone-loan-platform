<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInvestorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('investor_authenticated')) {
            return redirect()->route('investor.login');
        }

        return $next($request);
    }
}
