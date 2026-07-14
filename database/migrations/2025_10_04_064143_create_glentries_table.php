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
        Schema::create('glentries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('glbatches');
            $table->foreignId('account_id')->constrained('gl_accounts');
            $table->decimal('debit', 15, 2)->nullable();
            $table->decimal('credit', 15, 2)->nullable();
            $table->string('description')->nullable();

            $table->string('reference')->nullable(); // invoice number, payment ref, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glentries');
    }
};
