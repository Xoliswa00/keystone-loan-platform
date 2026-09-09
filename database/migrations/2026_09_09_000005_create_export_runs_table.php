<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per execution of an export profile — the evidentiary record of
     * what was sent, when, to whom, and with what column set. profile_snapshot
     * freezes the resolved config so a later edit to export_profiles cannot
     * rewrite history. Multiple runs per period are allowed (re-sends after a
     * rejection); "latest success per profile per period" is a query.
     */
    public function up(): void
    {
        Schema::create('export_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('export_profile_id')->constrained('export_profiles')->cascadeOnDelete();
            $table->char('period', 7);
            $table->unsignedInteger('snapshot_generation')->nullable();

            $table->string('status', 20)->default('pending')->comment('pending | building | built | sending | success | failed | skipped');
            $table->unsignedInteger('row_count')->default(0);

            $table->string('file_disk', 40)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_sha256', 64)->nullable();

            $table->string('transport', 20)->nullable()->comment('Copied from the recipient at run time');
            $table->json('transport_result')->nullable();
            $table->json('profile_snapshot')->nullable()->comment('Resolved columns/filters/format/recipient at run time');

            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable()->comment('Counts + slug + exception class only — never sample rows or PII');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('triggered_by', 40)->default('schedule')->comment('schedule | preview | <user id>');

            $table->timestamps();

            $table->index(['export_profile_id', 'period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_runs');
    }
};
