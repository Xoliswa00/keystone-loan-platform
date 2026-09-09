<?php

namespace App\Services\Export\Transports;

use App\Mail\DataExportMail;
use App\Models\ExportRecipient;
use App\Models\ExportRun;
use App\Services\Export\DatasetRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Emails the run's file as an attachment. Deliberately hard to misuse:
 *
 *  - refuses any destination domain not in config('exports.email_domain_allowlist')
 *  - refuses a recipient of kind 'bureau' (real bureau reporting is SFTP in
 *    Phase 2 — a full borrower file must not leave over cleartext SMTP)
 *  - refuses a profile whose column set contains any PII or internal column
 *
 * The net effect in Phase 1 (the only dataset is PII) is that email is usable
 * only for a deliberately non-PII extract to an allow-listed domain.
 */
class EmailTransport implements Transport
{
    public function __construct(private DatasetRegistry $registry) {}

    public function send(ExportRun $run, ExportRecipient $recipient): array
    {
        $addresses = array_values(array_filter((array) $recipient->email_recipients));

        if ($addresses === []) {
            throw new \RuntimeException('Recipient has no email addresses configured.');
        }

        if ($recipient->kind === 'bureau') {
            throw new \RuntimeException('Email transport is not permitted for a credit bureau recipient.');
        }

        $this->assertNoSensitiveColumns($run);
        $this->assertDomainsAllowed($addresses);

        $disk = $run->file_disk;
        $path = $run->file_path;
        $absolute = Storage::disk($disk)->path($path);

        $mailable = new DataExportMail(
            profileName: $run->profile->name,
            period: $run->period,
            rowCount: (int) $run->row_count,
            attachmentPath: $absolute,
            attachmentName: basename($path),
        );

        Mail::to($addresses)->send($mailable);

        return [
            'method' => 'email',
            'to' => $addresses,
            'attachment' => basename($path),
            'sha256' => $run->file_sha256,
            'sent_at' => now()->toIso8601String(),
        ];
    }

    private function assertNoSensitiveColumns(ExportRun $run): void
    {
        $dataset = $run->profile->dataset;
        $cols = $this->registry->columns($dataset);

        foreach ((array) $run->profile->columns as $c) {
            $meta = $cols[$c['source']] ?? null;
            if ($meta && (($meta['pii'] ?? false) || ($meta['internal'] ?? false))) {
                throw new \RuntimeException("Column '{$c['source']}' is sensitive and cannot be emailed.");
            }
        }
    }

    private function assertDomainsAllowed(array $addresses): void
    {
        $allow = array_map('strtolower', (array) config('exports.email_domain_allowlist', []));

        if ($allow === []) {
            throw new \RuntimeException('No email destination domains are allow-listed (DATA_EXPORTS_EMAIL_DOMAINS is empty).');
        }

        foreach ($addresses as $address) {
            $domain = strtolower((string) substr(strrchr($address, '@') ?: '', 1));
            if (! in_array($domain, $allow, true)) {
                throw new \RuntimeException("Destination domain '{$domain}' is not allow-listed.");
            }
        }
    }
}
