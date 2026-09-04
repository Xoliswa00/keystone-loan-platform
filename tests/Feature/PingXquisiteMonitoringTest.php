<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * monitoring:ping — the post-deploy pre-flight that authenticates against the
 * hub and names the exact misconfiguration.
 */
class PingXquisiteMonitoringTest extends TestCase
{
    private function config(string $url = 'https://hub.example.test', string $token = 'tok-000000000000000000000000000000'): void
    {
        config()->set('services.monitoring.url', $url);
        config()->set('services.monitoring.token', $token);
        config()->set('services.monitoring.slug', 'keystone');
    }

    public function test_missing_url_or_token_fails(): void
    {
        $this->config(url: '', token: '');

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('MONITORING_URL and/or MONITORING_TOKEN is not set')
            ->assertExitCode(1);
    }

    public function test_url_with_a_path_fails_before_any_request(): void
    {
        $this->config(url: 'https://hub.example.test/api/health-report');
        Http::fake();

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('has a path')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_ok_when_hub_authenticates(): void
    {
        $this->config();
        Http::fake(['*/api/health-status' => Http::response([
            'instance_id' => 7, 'status' => 'up', 'uptime_percentage' => '100.00', 'last_check' => '2026-09-03T21:00:00+00:00',
        ], 200)]);

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('OK — hub authenticated this instance')
            ->assertExitCode(0);
    }

    public function test_401_names_the_token_problem(): void
    {
        $this->config();
        Http::fake(['*/api/health-status' => Http::response(['error' => 'Invalid token'], 401)]);

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('the hub rejected the token')
            ->assertExitCode(1);
    }

    public function test_404_names_the_url_problem(): void
    {
        $this->config();
        Http::fake(['*/api/health-status' => Http::response('not found', 404)]);

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('endpoint not found')
            ->assertExitCode(1);
    }

    public function test_connection_failure_is_reported(): void
    {
        $this->config();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host'));

        $this->artisan('monitoring:ping')
            ->expectsOutputToContain('Could not reach the hub')
            ->assertExitCode(1);
    }
}
