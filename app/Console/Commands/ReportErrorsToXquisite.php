<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Forwards error-and-above system_logs rows to the central Xquisite monitoring
 * hub (POST {MONITORING_URL}/ingest/logs) so all apps' errors are visible in
 * one place. Scheduled every 5 minutes from app/Console/Kernel.php, alongside
 * the ReportHealthStatus heartbeat.
 *
 * Delivery is at-least-once: a batch is only marked forwarded_at once the hub
 * acknowledges it, and the hub de-duplicates on a per-row fingerprint, so a
 * re-sent batch after a failed run can't create duplicates.
 *
 * Only error+ is forwarded — deliberately. This command's own failure path
 * logs at warning level, which is below the threshold, so it can't feed back
 * into itself.
 */
class ReportErrorsToXquisite extends Command
{
    protected $signature = 'keystone:report-errors
        {--limit=100 : Max rows to forward in one run}
        {--backfill : Mark all currently-forwardable rows as sent without forwarding them (run once on first deploy)}';

    protected $description = 'Forward error-level system logs to the Xquisite monitoring hub';

    public function handle(): int
    {
        if (! config('services.monitoring.enabled')) {
            $this->info('Monitoring disabled (MONITORING_ENABLED=false) — nothing to do.');

            return self::SUCCESS;
        }

        $base = config('services.monitoring.url');
        $token = config('services.monitoring.token');

        if (! $base || ! $token) {
            Log::warning('keystone:report-errors is enabled but MONITORING_URL or MONITORING_TOKEN is missing.');

            return self::SUCCESS;
        }

        if ($this->option('backfill')) {
            $marked = SystemLog::forwardable()->update(['forwarded_at' => now()]);
            $this->info("Backfill: marked {$marked} existing error row(s) as already forwarded.");

            return self::SUCCESS;
        }

        $rows = SystemLog::forwardable()
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No un-forwarded error rows.');

            return self::SUCCESS;
        }

        $slug = config('services.monitoring.slug') ?: 'keystone';

        $events = $rows->map(fn (SystemLog $row) => [
            'level' => $row->level,
            'message' => $this->scrub((string) $row->message),
            'logged_at' => $row->logged_at->toIso8601String(),
            'fingerprint' => $slug.'-'.$row->id,
        ])->all();

        $target = rtrim($base, '/').'/ingest/logs';

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->acceptJson()
                ->post($target, ['events' => $events]);
        } catch (\Throwable $e) {
            Log::warning('Xquisite error-forward failed to send: '.$e->getMessage());
            $this->warn("Send to {$target} failed ({$e->getMessage()}) — will retry next run.");
            $this->line('  Check MONITORING_URL host/DNS/TLS. Run `php artisan monitoring:ping` to diagnose.');

            return self::SUCCESS;
        }

        if (! $response->successful()) {
            Log::warning('Xquisite error-forward rejected by hub.', [
                'target' => $target,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);
            $this->warn("Hub returned {$response->status()} for {$target} — will retry next run.");
            $this->line('  '.match ($response->status()) {
                401 => 'MONITORING_TOKEN does not match this instance on the hub, or the instance is inactive.',
                404 => 'MONITORING_URL likely still has a path (must be scheme+host only), or the hub route is not deployed.',
                429 => 'Rate limited — batches are too frequent; the scheduler will catch up.',
                default => 'Run `php artisan monitoring:ping` to diagnose.',
            });

            return self::SUCCESS;
        }

        SystemLog::whereIn('id', $rows->pluck('id'))->update(['forwarded_at' => now()]);

        $body = $response->json();
        $this->info(sprintf(
            'Forwarded %d row(s) — hub accepted %s, duplicates %s.',
            $rows->count(),
            $body['accepted'] ?? '?',
            $body['duplicates'] ?? '?',
        ));

        return self::SUCCESS;
    }

    /**
     * Redact obvious PII / secrets before a message leaves this server. The hub
     * viewer is staff-only; this is defence-in-depth, not the only control.
     */
    private function scrub(string $s): string
    {
        return preg_replace(
            [
                '/\b\d{13}\b/',                                  // SA ID number
                '/[\w.+-]+@[\w-]+\.[\w.-]+/',                    // email address
                '/(password|token|secret|api[_-]?key)=\S+/i',    // key=value secrets
                '/Bearer\s+\S+/i',                               // bearer tokens
            ],
            [
                '«redacted-id»',
                '«redacted-email»',
                '$1=«redacted»',
                'Bearer «redacted»',
            ],
            $s
        );
    }
}
