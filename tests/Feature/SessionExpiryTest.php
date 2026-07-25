<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * A stale session/CSRF token throws TokenMismatchException on the next form
 * submit. Laravel's base Handler::prepareException() converts that into a
 * plain HttpException(419, ...) before renderable callbacks are matched by
 * type — so App\Exceptions\Handler registers its callback against
 * HttpException + a status-code check, not TokenMismatchException directly.
 * These tests render the exception directly (rather than driving it through
 * a real POST) because Laravel's CSRF middleware short-circuits under
 * app()->runningUnitTests(), so a real request never actually throws it in
 * this suite.
 */
class SessionExpiryTest extends TestCase
{
    public function test_token_mismatch_redirects_to_login_with_flash_message(): void
    {
        $request = Request::create('/admin/loans/1/approve', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->start();

        $response = $this->app->make(ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/login', $response->headers->get('Location'));
        $this->assertSame('Your session has expired. Please log in again.', $this->app['session.store']->get('error'));
    }

    public function test_token_mismatch_returns_json_for_json_requests(): void
    {
        $request = Request::create('/admin/loans/1/approve', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->start();

        $response = $this->app->make(ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('Your session has expired. Please log in again.', $response->getData()->message);
    }
}
