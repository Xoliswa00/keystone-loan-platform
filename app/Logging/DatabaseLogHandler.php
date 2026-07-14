<?php

namespace App\Logging;

use App\Models\SystemLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Mirrors every log record into the system_logs table alongside the normal
 * file channel, so admin/it_admin staff can query recent activity via the
 * API instead of tailing storage/logs/laravel.log. Deliberately stores only
 * level/channel/message — see the system_logs migration for why context and
 * stack traces are left out.
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            SystemLog::create([
                'level' => strtolower($record->level->getName()),
                'channel' => $record->channel,
                'message' => $record->message,
                'logged_at' => $record->datetime,
            ]);
        } catch (\Throwable $e) {
            // Never let log persistence itself break the request — the file
            // channel is still the source of truth if the DB is unavailable.
        }
    }
}
