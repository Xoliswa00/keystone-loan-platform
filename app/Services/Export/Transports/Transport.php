<?php

namespace App\Services\Export\Transports;

use App\Models\ExportRecipient;
use App\Models\ExportRun;

interface Transport
{
    /**
     * Deliver the run's file to the recipient. Returns a JSON-serialisable
     * result stored on export_runs.transport_result. Must throw on failure.
     *
     * @return array<string,mixed>
     */
    public function send(ExportRun $run, ExportRecipient $recipient): array;
}
