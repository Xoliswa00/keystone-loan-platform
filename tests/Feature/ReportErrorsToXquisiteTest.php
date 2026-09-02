<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * keystone:report-errors ships error-and-above system_logs rows to the central
 * Xquisite hub exactly once, scrubbing obvious PII on the way out.
 */
class ReportErrorsToXquisiteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.monitoring.enabled', true);
        config()->set('services.monitoring.url', 'https://hub.example.test');
        config()->set('services.monitoring.token', 'test-token-000000000000000000000000');
    }

    private function makeLog(string $level, string $message): SystemLog
    {
        return SystemLog::create([
            'level'     => $level,
            'channel'   => 'testing',
            'message'   => $message,
            'logged_at' => now()->subMinute(),
        ]);
    }

    public function test_it_forwards_error_rows_and_marks_them(): void
    {
        Http::fake(['*/ingest/logs' => Http::response(['accepted' => 1, 'duplicates' => 0, 'instance' => 'keystone'], 200)]);

        $log = $this->makeLog('error', 'Something broke');

        $this->artisan('keystone:report-errors')->assertSuccessful();

        $this->assertNotNull($log->fresh()->forwarded_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hub.example.test/ingest/logs'
                && $request->hasHeader('Authorization', 'Bearer test-token-000000000000000000000000')
                && $request['events'][0]['level'] === 'error'
                && $request['events'][0]['fingerprint'] !== null
                && str_starts_with($request['events'][0]['fingerprint'], 'keystone-');
        });
    }

    public function test_it_ignores_below_error_levels(): void
    {
        Http::fake(['*/ingest/logs' => Http::response(['accepted' => 0, 'duplicates' => 0], 200)]);

        $warn = $this->makeLog('warning', 'just a warning');
        $info = $this->makeLog('info', 'fyi');

        $this->artisan('keystone:report-errors')->assertSuccessful();

        $this->assertNull($warn->fresh()->forwarded_at);
        $this->assertNull($info->fresh()->forwarded_at);
        Http::assertNothingSent();
    }

    public function test_it_scrubs_pii_before_sending(): void
    {
        Http::fake(['*/ingest/logs' => Http::response(['accepted' => 1, 'duplicates' => 0], 200)]);

        $this->makeLog('critical', 'Login failed for 8001015009087 user bob@example.com password=hunter2 Bearer abc.def.ghi');

        $this->artisan('keystone:report-errors')->assertSuccessful();

        Http::assertSent(function ($request) {
            $msg = $request['events'][0]['message'];

            return ! str_contains($msg, '8001015009087')
                && ! str_contains($msg, 'bob@example.com')
                && ! str_contains($msg, 'hunter2')
                && ! str_contains($msg, 'abc.def.ghi')
                && str_contains($msg, '«redacted-id»')
                && str_contains($msg, '«redacted-email»');
        });
    }

    public function test_it_does_not_mark_rows_when_hub_rejects(): void
    {
        Http::fake(['*/ingest/logs' => Http::response('boom', 500)]);

        $log = $this->makeLog('error', 'will not stick');

        $this->artisan('keystone:report-errors')->assertSuccessful();

        $this->assertNull($log->fresh()->forwarded_at);
    }

    public function test_backfill_marks_without_sending(): void
    {
        Http::fake();

        $log = $this->makeLog('error', 'historic');

        $this->artisan('keystone:report-errors', ['--backfill' => true])->assertSuccessful();

        $this->assertNotNull($log->fresh()->forwarded_at);
        Http::assertNothingSent();
    }

    public function test_disabled_config_is_a_noop(): void
    {
        config()->set('services.monitoring.enabled', false);
        Http::fake();

        $log = $this->makeLog('error', 'ignored');

        $this->artisan('keystone:report-errors')->assertSuccessful();

        $this->assertNull($log->fresh()->forwarded_at);
        Http::assertNothingSent();
    }
}
