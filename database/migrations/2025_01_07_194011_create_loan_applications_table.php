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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // Foreign key to the users table
            $table->foreignId('reviewer_id')->constrained('users');

            $table->enum('loan_type', ['personal', 'home', 'business']); // Type of loan
            $table->decimal('loan_amount', 15, 2); // Loan amount with 2 decimal places
            $table->text('purpose'); // Purpose of the loan
            $table->text('reason')->nullable(); // Purpose of the loan

            $table->text('collateral')->nullable(); // Collateral information (optional)
            $table->enum('status', ['pending', 'approved', 'rejected', 'deleted'])->default('pending'); // Application status
            $table->date('approval_date')->nullable(); // Approval date (nullable until approved)
            $table->string('credit_score_report')->nullable(); // File path for credit score report
            $table->string('bank_statement')->nullable(); // File path for bank statement
            $table->string('payslips')->nullable(); // File path for payslips
            $table->text('loan_purpose')->nullable(); // Loan purpose (additional details)
            $table->boolean('arrears')->default(false); // Whether the applicant has arrears (default is false)
            $table->timestamps(); // Automatically adds created_at and updated_at columns
            $table->boolean('terms_conditions')->default(false);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
