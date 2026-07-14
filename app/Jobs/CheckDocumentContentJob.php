<?php

namespace App\Jobs;

use App\Models\CustomerDocument;
use App\Services\DocumentContentCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDocumentContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public CustomerDocument $document) {}

    public function handle(DocumentContentCheckService $service): void
    {
        $service->check($this->document);
    }
}
