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
        'PROCESSED'
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
