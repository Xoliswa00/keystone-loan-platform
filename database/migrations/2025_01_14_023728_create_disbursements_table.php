
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
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans');  // Foreign key to loan table
            $table->decimal('disbursed_amount', 15, 2);  // Amount disbursed to the customer
            $table->enum('disbursement_method', ['bank_transfer', 'cash', 'check', 'other'])->default('bank_transfer');  // Method of disbursement
            $table->date('disbursement_date')->default(Now());  // Date of disbursement
            $table->foreignId('approver_id')->constrained('users');  // The ID of the user who approved the disbursement
            $table->string('payment_reference')->nullable();  // Payment reference, for tracking the payment
            $table->string('proof_of_payment')->nullable();  // File path to proof of payment (e.g., screenshot, PDF)
            $table->foreignId('payment_realiser_id')->nullable()->constrained('users');  // The ID of the user who processed the payment (the payment realiser)
            $table->enum('status', ['waiting_for_approval', 'approved', 'rejected', 'disbursed'])->default('waiting_for_approval'); // Status of the disbursement
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
    }
};
