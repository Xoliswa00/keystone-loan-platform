<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The migration that originally created this table
        // (2025_12_26_213223_create_import_batches_table) was removed from
        // the repo after already running against every pre-existing
        // database. Guard so this migration is safe both on fresh installs
        // and on databases where the table already exists.
        if (Schema::hasTable('import_batches')) {
            return;
        }

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();

            $table->string('source'); // nupay, bank, payroll, etc
            $table->string('original_filename');
            $table->string('stored_path');

            $table->string('checksum')->index(); // duplicate detection
            $table->enum('status', [
                'UPLOADED',
                'CAPTURED',
                'FAILED_CAPTURE',
                'READY_FOR_VALIDATION',
                'VALIDATED',
                'FAILED_VALIDATION',
                'PROCESSED',
            ])->default('UPLOADED');
            $table->text('error_message')->nullable();
            $table->string('processed_by')->nullable(); // user who processed the batch
            $table->string('import_ref')->unique(); // unique reference for tracking

            $table->unsignedInteger('row_count')->default(0);
            $table->json('meta')->nullable(); // bank name, account no, period, etc
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
