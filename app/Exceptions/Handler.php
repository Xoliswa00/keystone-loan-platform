<?php

namespace App\Exceptions;

use App\Models\AuditLog;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // A stale session/CSRF token (session expired, or the tab sat open
        // past the session lifetime) throws TokenMismatchException on the
        // next form submit — Laravel's default is a bare 419 "Page Expired"
        // page with no way back in. Redirect to login with an explanatory
        // flash instead, same as an expired GET request already does via
        // App\Http\Middleware\Authenticate::redirectTo().
        //
        // Registered against HttpException rather than TokenMismatchException
        // directly: the base Handler's prepareException() already converts
        // TokenMismatchException into a plain HttpException(419, ...) before
        // renderable callbacks are matched by type, so a callback typed to
        // TokenMismatchException itself would never actually match.
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please log in again.'], 419);
            }

            return redirect()->guest(route('login'))
                ->with('error', 'Your session has expired. Please log in again.');
        });

        // 403s (role middleware, ownership abort_unless() checks scattered
        // across controllers) are in the base Handler's default "don't
        // report" list — they render fine but were never logged anywhere,
        // so a role trying a page outside their remit left no trail. Record
        // it to the same audit_logs table the Audit Report page (IT-only,
        // see routes/web.php's reports.audit-log) already reads, filterable
        // there by event=access_denied.
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403 && $request->user()) {
                AuditLog::record(
                    'access_denied',
                    $request->user(),
                    [],
                    ['method' => $request->method(), 'path' => $request->path(), 'route' => optional($request->route())->getName()],
                    $e->getMessage() ?: 'Blocked by role or ownership check.'
                );
            }

            return null; // don't change the response — just log, then let Laravel render its normal 403 page
        });
    }
}
