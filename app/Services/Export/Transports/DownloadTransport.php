<?php

namespace App\Services\Export\Transports;

use App\Models\ExportRecipient;
use App\Models\ExportRun;

/**
 * No external transmission — the file stays on disk and a staff member
 * downloads it from the run-history screen. The safe default: nothing leaves
 * the building automatically.
 */
class DownloadTransport implements Transport
{
    public function send(ExportRun $run, ExportRecipient $recipient): array
    {
        return [
            'method' => 'download',
            'note' => 'File retained for manual download from run history.',
            'delivered_at' => now()->toIso8601String(),
        ];
    }
}
