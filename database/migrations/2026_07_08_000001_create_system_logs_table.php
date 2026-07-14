<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured, queryable capture of everything the app logs — the flat
     * storage/logs/laravel.log file still gets written (SystemLogController
     * still reads it for the admin dashboard), but a file isn't a reasonable
     * basis for an API. Deliberately message/level/channel only, no stack
     * traces or context arrays — those can carry query fragments or tokens
     * from exception messages, and this table is exposed via API.
     */
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20);
            $table->string('channel', 40)->nullable();
            $table->text('message');
            $table->timestamp('logged_at');

            $table->index(['level', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
