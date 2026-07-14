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
        Schema::create('cashbook_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbook_account_id')->constrained('cashbook_accounts');
            $table->foreignId('batch_id')->nullable()->constrained('cashbook_batches');

            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['receipt', 'payment']);
            $table->enum('payment_method', ['cash', 'eft', 'debit_card', 'cheque'])->nullable();
            $table->string('status')->default('pending'); // pending, approved, posted

            // 🔗 Integration with submodules
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('vendor_id')->nullable()->constrained('apvendors');
            $table->foreignId('loan_id')->nullable()->constrained('loans');

            // 🔗 Accounting linkage
            $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts');
            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashbook_transactions');
    }
};
