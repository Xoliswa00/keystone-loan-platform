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
        Schema::create('arbatch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arbatch_id')->constrained('arbatches')->onDelete('cascade');
            $table->foreignId('gl_account_id')->constrained('gl_accounts');
            $table->enum('entry_type', ['debit', 'credit']); // which side this is
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->foreignId('loan_id')->nullable()->constrained('loans');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arbatch_entries');
    }
};
