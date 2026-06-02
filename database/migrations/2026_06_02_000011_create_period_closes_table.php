<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_closes', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // YYYY-MM
            $table->foreignId('closed_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Bank statement staging table (for reconciliation)
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->onDelete('cascade');
            $table->string('import_ref');
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->decimal('debit_amount', 12, 2)->default(0);
            $table->decimal('credit_amount', 12, 2)->default(0);
            $table->decimal('running_balance', 12, 2)->nullable();

            // Matching
            $table->enum('match_status', ['unmatched', 'matched', 'exception'])->default('unmatched');
            $table->unsignedBigInteger('matched_nupay_staging_id')->nullable();
            $table->unsignedBigInteger('matched_loan_repayment_id')->nullable();
            $table->text('match_note')->nullable();

            $table->timestamps();
            $table->index(['import_ref', 'transaction_date']);
            $table->index('match_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('period_closes');
    }
};
