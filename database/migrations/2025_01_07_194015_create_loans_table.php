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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            // Foreign key reference to loan applications
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');

            // Loan details
            $table->enum('loan_type', ['personal', 'home', 'business']);
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('loan_term'); // in months
            $table->text('collateral')->nullable();
            $table->decimal('approved_amount', 15, 2);

            // Loan status and dates
            $table->enum('status', ['pending', 'approved', 'rejected', 'disbursed'])->default('pending');
            $table->date('disbursed_date')->nullable(); // The date the loan is disbursed

            // Payment tracking
            $table->decimal('remaining_balance', 15, 2)->default(0);
            $table->date('next_payment_date')->nullable();
            $table->integer('installment_frequency')->nullable(); // Monthly, quarterly, etc.

            // Loan approval/rejection details
            $table->text('approval_comments')->nullable();
            $table->text('rejection_comments')->nullable();

            // Admin and approval-related fields
            $table->foreignId('approver_id')->nullable()->constrained('users'); // Who approved the loan
            $table->timestamp('approved_at')->nullable(); // When the loan was approved
            $table->timestamp('rejected_at')->nullable(); // When the loan was rejected

            // Admin tracking
            $table->foreignId('admin_id')->nullable()->constrained('users'); // Who processed the application
            $table->timestamp('processed_at')->nullable(); // When the loan application was processed by the admin

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
