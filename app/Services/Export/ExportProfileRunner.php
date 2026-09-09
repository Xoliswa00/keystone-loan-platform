<?php

namespace App\Services\Export;

use App\Models\AuditLog;
use App\Models\ExportProfile;
use App\Models\ExportRun;
use App\Services\Export\Formatters\CsvFormatter;
use App\Services\Export\Transports\DownloadTransport;
use App\Services\Export\Transports\EmailTransport;
use App\Services\Export\Transports\Transport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs one export profile for one period: builds the file from the flat
 * dataset, writes it to disk, hands it to the recipient's transport, and
 * records an export_runs row either way. One profile failing never aborts a
 * batch. Honours config('exports.enabled'); a preview ($dryRun) still requires
 * the flag off to be harmless but never transmits and never audits as a send.
 */
class ExportProfileRunner
{
    public function __construct(
        private DatasetRegistry $registry,
        private CsvFormatter $csv,
    ) {}

    public function run(ExportProfile $profile, string $period, string $triggeredBy, bool $dryRun = false): ExportRun
    {
        $recipient = $profile->recipient;

        $run = ExportRun::create([
            'export_profile_id' => $profile->id,
            'period' => $period,
            'status' => 'pending',
            'transport' => $dryRun ? 'preview' : ($recipient->transport ?? 'download'),
            'triggered_by' => $triggeredBy,
        ]);

        try {
            if (! $dryRun && ! config('exports.enabled')) {
                return $this->finish($run, 'skipped', ['error_message' => 'Data exports disabled (DATA_EXPORTS_ENABLED=false).']);
            }

            $generation = null;
            if ($profile->dataset === 'account_snapshot') {
                $generation = \App\Models\AccountSnapshot::where('period', $period)->max('generation');
                if (! $generation) {
                    return $this->finish($run, 'skipped', ['error_message' => "No account snapshot exists for {$period}."]);
                }
            }

            $run->update([
                'status' => 'building',
                'started_at' => now(),
                'snapshot_generation' => $generation,
                'profile_snapshot' => $this->profileSnapshot($profile),
            ]);

            $query = $this->buildQuery($profile, $period, $generation, includeDisputed: $dryRun);

            $count = (clone $query)->toBase()->getCountForPagination();
            $max = (int) config('exports.max_rows_per_run', 250000);
            if ($count > $max) {
                throw new \RuntimeException("Row count {$count} exceeds max_rows_per_run ({$max}).");
            }

            $content = $this->csv->format($query->cursor(), $profile);

            $path = trim((string) config('exports.path', 'data_exports'), '/')
                .'/'.$profile->slug
                .'/'.$this->resolveFilename($profile, $period);
            $disk = (string) config('exports.disk', 'local');
            Storage::disk($disk)->put($path, $content);

            $run->update([
                'status' => $dryRun ? 'built' : 'sending',
                'row_count' => $count,
                'file_disk' => $disk,
                'file_path' => $path,
                'file_size' => strlen($content),
                'file_sha256' => hash('sha256', $content),
            ]);

            if ($dryRun) {
                return $this->finish($run, 'built', []);
            }

            $result = $this->transportFor($recipient)->send($run->fresh(), $recipient);

            AuditLog::record('exported', $profile, [], [
                'period' => $period,
                'generation' => $generation,
                'rows' => $count,
                'run_id' => $run->id,
                'recipient' => $recipient->slug,
            ]);

            return $this->finish($run, 'success', ['transport_result' => $result]);
        } catch (\Throwable $e) {
            // Scrubbed context only — this may be forwarded to an external hub.
            Log::error('Data export run failed', [
                'profile' => $profile->slug,
                'period' => $period,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return $this->finish($run, 'failed', [
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'error_context' => ['exception' => get_class($e), 'profile' => $profile->slug],
            ]);
        }
    }

    // ── internals ──────────────────────────────────────────────────────────

    private function finish(ExportRun $run, string $status, array $extra): ExportRun
    {
        $run->update(array_merge(['status' => $status, 'finished_at' => now()], $extra));

        return $run->fresh();
    }

    private function buildQuery(ExportProfile $profile, string $period, ?int $generation, bool $includeDisputed)
    {
        $query = $this->registry->query($profile->dataset)->where('period', $period);

        if ($generation !== null) {
            $query->where('generation', $generation);
        }

        // NCA s.72: disputed adverse data is not shipped as undisputed. Preview
        // keeps disputed rows visible so staff can see what would be held back.
        if (! $includeDisputed) {
            $query->where('under_dispute', false);
        }

        $filterable = $this->registry->filterable($profile->dataset);
        foreach ((array) ($profile->filters ?? []) as $field => $values) {
            if (in_array($field, $filterable, true)) {
                $query->whereIn($field, array_values((array) $values));
            }
        }

        $sources = collect($profile->columns)->pluck('source')->push('id')->unique()->values()->all();

        return $query->select($sources);
    }

    private function resolveFilename(ExportProfile $profile, string $period): string
    {
        $pattern = $profile->filename_pattern ?: '{recipient_slug}_{profile_slug}_{period}.csv';

        $name = strtr($pattern, [
            '{recipient_slug}' => $profile->recipient->slug,
            '{profile_slug}' => $profile->slug,
            '{period}' => $period,
            '{Ymd}' => now()->format('Ymd'),
            '{Ymd_His}' => now()->format('Ymd_His'),
        ]);

        // Never allow path traversal or separators in a resolved filename.
        $name = str_replace(['..', '/', '\\', "\0"], '', $name);

        return $name !== '' ? $name : "{$profile->slug}_{$period}.csv";
    }

    private function profileSnapshot(ExportProfile $profile): array
    {
        return [
            'columns' => $profile->columns,
            'filters' => $profile->filters,
            'format' => $profile->format,
            'delimiter' => $profile->delimiter,
            'dataset' => $profile->dataset,
            'recipient' => [
                'slug' => $profile->recipient->slug,
                'kind' => $profile->recipient->kind,
                'transport' => $profile->recipient->transport,
                'email_recipients' => $profile->recipient->email_recipients,
            ],
        ];
    }

    private function transportFor(\App\Models\ExportRecipient $recipient): Transport
    {
        return match ($recipient->transport) {
            'email' => app(EmailTransport::class),
            default => app(DownloadTransport::class),
        };
    }
}
