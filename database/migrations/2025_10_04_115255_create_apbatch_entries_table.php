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
        Schema::create('apbatch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('apbatches');
            $table->foreignId('invoice_id')->nullable()->constrained('apinvoices');
            $table->foreignId('vendor_id')->nullable()->constrained('apvendors');
            $table->decimal('debit', 15, 2)->nullable();
            $table->decimal('credit', 15, 2)->nullable();
            $table->string('entry_type'); // APINV, APPAYMENT, etc.
            $table->foreignId('gl_account_id')->constrained('glamfs');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apbatch_entries');
    }
};
