<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Post-deploy pre-flight for the Xquisite monitoring wiring. Actively calls
 * {MONITORING_URL}/api/health-status (the GET endpoint that only validates the
 * bearer token) and reports either OK + the resolved instance, or the exact
 * misconfiguration — so a silent runtime 401/404 after a deploy becomes a
 * green/red check you run on purpose.
 *
 * Run it once after every deploy that touches MONITORING_* env vars, before
 * trusting the 5-minute scheduler.
 */
class PingXquisiteMonitoring extends Command
{
    protected $signature = 'monitoring:ping';

    protected $description = 'Check MONITORING_URL / MONITORING_TOKEN actually authenticate against the Xquisite hub';

    public function handle(): int
    {
        $base = (string) config('services.monitoring.url');
        $token = (string) config('services.monitoring.token');
        $slug = (string) config('services.monitoring.slug');

        if ($base === '' || $token === '') {
            $this->error('MONITORING_URL and/or MONITORING_TOKEN is not set.');
            $this->line('  Set all four in .env: MONITORING_ENABLED, MONITORING_URL, MONITORING_TOKEN, MONITORING_SLUG — then `php artisan config:cache`.');

            return self::FAILURE;
        }

        // MONITORING_URL must be scheme+host only — every sender appends its own
        // path (/ingest/logs, /api/health-report, /js-error).
        $path = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
        if ($path !== '') {
            $this->error("MONITORING_URL has a path (\"{$path}\") — it must be the hub base URL only.");
            $this->line('  Expected e.g. https://xquisite.brightfinance-x.co.za  (no /api/..., no trailing path)');

            return self::FAILURE;
        }

        $target = rtrim($base, '/').'/api/health-status';
        $this->line("Pinging {$target} as slug \"{$slug}\" …");

        try {
            $response = Http::withToken($token)->timeout(10)->acceptJson()->get($target);
        } catch (\Throwable $e) {
            $this->error("Could not reach the hub: {$e->getMessage()}");
            $this->line('  Check the MONITORING_URL host resolves and TLS is valid from this box (curl -I '.$base.').');

            return self::FAILURE;
        }

        if ($response->status() === 401) {
            $this->error('401 — the hub rejected the token.');
            $this->line('  MONITORING_TOKEN does not match this instance\'s api_token on the hub, or the instance is inactive.');
            $this->line('  Fix: hub → /admin/monitoring → the "'.$slug.'" instance → copy the API Token exactly, confirm it is Active.');

            return self::FAILURE;
        }

        if ($response->status() === 404) {
            $this->error('404 — endpoint not found at that URL.');
            $this->line('  MONITORING_URL is probably still a full endpoint, or the hub has not deployed the /api/health-status route.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("Unexpected {$response->status()} from the hub.");
            $this->line('  '.Str::limit($response->body(), 300));

            return self::FAILURE;
        }

        $body = $response->json();
        $this->info('OK — hub authenticated this instance.');
        $this->line(sprintf(
            '  instance_id=%s  status=%s  uptime=%s%%  last_check=%s',
            $body['instance_id'] ?? '?',
            $body['status'] ?? '?',
            $body['uptime_percentage'] ?? '?',
            $body['last_check'] ?? 'never',
        ));

        return self::SUCCESS;
    }
}
