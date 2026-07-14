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
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans');
            $table->foreignId('user_id')->constrained('users'); // Link repayment to a user
            $table->decimal('payment_amount', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0); // Track total payments made for this loan
            $table->date('payment_date');
            $table->date('due_date'); // Add a due date for repayments
            $table->enum('status', ['paid', 'pending', 'overdue']);
            $table->enum('payment_method', ['bank_transfer', 'cash', 'card', 'mobile_payment'])->default('bank_transfer');
            $table->string('payment_reference')->nullable(); // Store unique reference for each payment
            $table->text('notes')->nullable(); // Additional notes for repayment
            $table->foreignId('payment_received_by')->nullable()->constrained('users'); // Track the user who processed the payment
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
