<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemLogController extends Controller
{
    const LOG_PATH = 'logs/laravel.log';

    const MAX_LINES = 500;   // max lines to parse per request

    const TAIL_BYTES = 500000; // read last ~500KB of log file

    /**
     * Main log dashboard — errors, warnings, failed jobs, health.
     */
    public function index(Request $request)
    {
        $level = $request->get('level', 'all');
        $search = $request->get('search', '');
        $date = $request->get('date', now()->toDateString());

        $entries = $this->parseLog($level, $search, $date);
        $failedJobs = $this->getFailedJobs();
        $health = $this->systemHealth();

        // Summary counts
        $counts = [
            'error' => $this->countByLevel('ERROR', $date),
            'warning' => $this->countByLevel('WARNING', $date),
            'info' => $this->countByLevel('INFO', $date),
        ];

        return view('admin.system.logs', compact(
            'entries', 'failedJobs', 'health', 'counts',
            'level', 'search', 'date'
        ));
    }

    /**
     * Retry a failed queue job.
     */
    public function retryJob(Request $request)
    {
        $uuid = $request->input('uuid');

        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => [$uuid]]);

            return back()->with('success', "Job {$uuid} queued for retry.");
        } catch (\Exception $e) {
            return back()->with('error', 'Retry failed: '.$e->getMessage());
        }
    }

    /**
     * Clear all failed jobs.
     */
    public function clearFailed()
    {
        DB::table('failed_jobs')->truncate();

        return back()->with('success', 'All failed jobs cleared.');
    }

    /**
     * Download the full log file.
     */
    public function download()
    {
        $path = storage_path(self::LOG_PATH);
        if (! file_exists($path)) {
            return back()->with('error', 'Log file not found.');
        }

        return response()->download($path, 'laravel-'.now()->format('Y-m-d').'.log');
    }

    /**
     * Manually trigger a cron-only job — allowlisted, not a raw command
     * string, since this reaches Artisan::call().
     */
    public function runJob(Request $request)
    {
        $request->validate(['job' => 'required|in:escalate-arrears,send-payment-reminders']);

        try {
            \Illuminate\Support\Facades\Artisan::call('keystone:'.$request->job);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return back()->with('success', $output !== '' ? $output : 'Job completed with no output.');
        } catch (\Exception $e) {
            return back()->with('error', 'Job failed: '.$e->getMessage());
        }
    }

    /**
     * Clear the log file (archive first).
     */
    public function clearLog()
    {
        $path = storage_path(self::LOG_PATH);
        $archive = storage_path('logs/laravel-'.now()->format('Y-m-d-His').'.log');

        if (file_exists($path)) {
            rename($path, $archive);
            file_put_contents($path, '');
        }

        return back()->with('success', 'Log cleared. Archive saved.');
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function parseLog(string $level, string $search, string $date): array
    {
        $path = storage_path(self::LOG_PATH);

        if (! file_exists($path)) {
            return [];
        }

        // Read the tail of the file to avoid loading gigabytes
        $size = filesize($path);
        $offset = max(0, $size - self::TAIL_BYTES);

        $fh = fopen($path, 'r');
        fseek($fh, $offset);
        $content = fread($fh, self::TAIL_BYTES);
        fclose($fh);

        // If we truncated, skip the first (likely partial) line
        if ($offset > 0) {
            $content = substr($content, strpos($content, "\n") + 1);
        }

        $lines = explode("\n", $content);
        $entries = [];
        $current = null;

        // Laravel log format: [YYYY-MM-DD HH:MM:SS] env.LEVEL: message {"context"} {"extra"}
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/';

        foreach (array_reverse($lines) as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                    if (count($entries) >= self::MAX_LINES) {
                        break;
                    }
                }

                $entryDate = substr($m[1], 0, 10);
                $entryLevel = strtoupper($m[2]);

                // Filter by date
                if ($entryDate !== $date) {
                    $current = null;

                    continue;
                }

                // Filter by level
                if ($level !== 'all' && strtolower($entryLevel) !== strtolower($level)) {
                    $current = null;

                    continue;
                }

                $message = $m[3];

                // Filter by search
                if ($search && stripos($message, $search) === false) {
                    $current = null;

                    continue;
                }

                // Extract JSON context if present
                $context = null;
                if (preg_match('/(\{.+\})\s*(\{.*\})?$/', $message, $ctx)) {
                    try {
                        $context = json_decode($ctx[1], true);
                        $message = trim(substr($message, 0, strrpos($message, '{')));
                    } catch (\Exception $e) {
                        // not valid JSON — leave message as is
                    }
                }

                // QueryException messages embed the full SQL statement + every
                // bound value inline (see the NuPay import errors — a 47-column
                // INSERT running to 2000+ characters on one line). Left in the
                // one-line preview, that pushes the whole page wider than the
                // screen instead of staying inside the card. Split it out so
                // the preview stays a short, readable summary and the SQL is
                // only shown (scrollable, contained) when the entry is expanded.
                [$message, $sql] = $this->splitSqlMessage(rtrim($message));

                $current = [
                    'timestamp' => $m[1],
                    'level' => $entryLevel,
                    'message' => $message,
                    'sql' => $sql,
                    'context' => $context,
                    'extra' => [],
                ];
            } elseif ($current && trim($line)) {
                // Stack trace continuation
                $current['extra'][] = $line;
            }
        }

        if ($current) {
            $entries[] = $current;
        }

        return array_slice($entries, 0, self::MAX_LINES);
    }

    /**
     * Splits a Laravel QueryException-style message — "<reason> (Connection:
     * mysql, SQL: insert into ... values (...))" — into a short summary and
     * the full SQL. Non-greedy up to the first "(Connection:" so the summary
     * is just the human-readable part; greedy capture after "SQL:" so the
     * SQL's own closing parens don't truncate it early.
     *
     * @return array{0: string, 1: ?string} [summary, sql]
     */
    protected function splitSqlMessage(string $message): array
    {
        if (preg_match('/^(.*?)\s*\(Connection:\s*\w+,\s*SQL:\s*(.+)\)$/s', $message, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$message, null];
    }

    protected function countByLevel(string $level, string $date): int
    {
        $path = storage_path(self::LOG_PATH);
        if (! file_exists($path)) {
            return 0;
        }

        $count = 0;
        $pattern = "/^\[{$date}.*?\] \w+\.{$level}: /";
        $fh = fopen($path, 'r');

        while (! feof($fh)) {
            $line = fgets($fh, 4096);
            if (preg_match($pattern, $line)) {
                $count++;
            }
        }

        fclose($fh);

        return $count;
    }

    protected function getFailedJobs(): array
    {
        try {
            return DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->take(50)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);

                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'display_name' => $payload['displayName'] ?? 'Unknown',
                        'failed_at' => $job->failed_at,
                        'exception' => $this->extractException($job->exception),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return []; // failed_jobs table might not exist yet
        }
    }

    protected function extractException(string $exception): string
    {
        // Return just the first line (exception class + message)
        return Str::limit(explode("\n", $exception)[0] ?? $exception, 120);
    }

    protected function systemHealth(): array
    {
        $health = [];

        // Queue pending jobs
        try {
            $health['queue_pending'] = DB::table('jobs')->count();
            $health['queue_failed'] = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            $health['queue_pending'] = '—';
            $health['queue_failed'] = '—';
        }

        // Log file size
        $logPath = storage_path(self::LOG_PATH);
        $health['log_size'] = file_exists($logPath)
            ? $this->formatBytes(filesize($logPath))
            : '0 B';

        // Storage disk space
        $health['disk_free'] = $this->formatBytes(disk_free_space(storage_path()));
        $health['disk_total'] = $this->formatBytes(disk_total_space(storage_path()));

        // Last GL reconciliation
        try {
            $lastRecon = DB::table('glbatches')->orderBy('posted_at', 'desc')->value('posted_at');
            $health['last_gl_post'] = $lastRecon ? Carbon::parse($lastRecon)->diffForHumans() : 'Never';
        } catch (\Exception $e) {
            $health['last_gl_post'] = '—';
        }

        // Last Nu-Pay import
        try {
            $lastImport = DB::table('import_batches')->where('source', 'nupay')
                ->orderBy('created_at', 'desc')->value('created_at');
            $health['last_nupay_import'] = $lastImport ? Carbon::parse($lastImport)->diffForHumans() : 'Never';
        } catch (\Exception $e) {
            $health['last_nupay_import'] = '—';
        }

        // Current period status
        try {
            $currentPeriod = \App\Models\FinancialPeriod::current();
            $health['current_period'] = $currentPeriod?->displayLabel() ?? now()->format('F Y');
            $health['current_period_status'] = ucfirst($currentPeriod?->status ?? 'open');
        } catch (\Exception $e) {
            $health['current_period'] = '—';
            $health['current_period_status'] = '—';
        }

        return $health;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
