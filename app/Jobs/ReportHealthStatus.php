<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportHealthStatus
{
    use Dispatchable;

    /**
     * Send a heartbeat to Xquisite so it can alert if this site stops checking in.
     *
     * Deliberately does NOT implement ShouldQueue — a heartbeat that depends on a
     * queue worker being alive can't tell you the worker itself died. The
     * scheduler (routes/console.php) runs this synchronously every 5 minutes.
     */
    public function handle(): void
    {
        if (! config('services.monitoring.enabled')) {
            return;
        }

        $url = config('services.monitoring.url');
        $token = config('services.monitoring.token');

        if (! $url || ! $token) {
            Log::warning('Health status reporting is enabled but MONITORING_URL or MONITORING_TOKEN is missing.');

            return;
        }

        $dbConnectionOk = true;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbConnectionOk = false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->post($url, [
                    'status' => $dbConnectionOk ? 'up' : 'down',
                    'db_connection' => $dbConnectionOk,
                    'error_message' => $dbConnectionOk ? null : 'Database connection failed',
                ]);

            if (! $response->successful()) {
                Log::warning('Health status heartbeat rejected by Xquisite.', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Health status heartbeat failed to send: '.$e->getMessage());
        }
    }
}
