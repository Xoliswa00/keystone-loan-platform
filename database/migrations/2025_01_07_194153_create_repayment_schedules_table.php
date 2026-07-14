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
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')
                ->constrained('loan_applications')
                ->onDelete('cascade'); // Ensure repayment schedules are removed when the loan is deleted
            $table->decimal('emi_amount', 15, 2)->default(0); // EMI Amount should not be zero, default to 0 if needed
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'rejected'])->default('pending'); // Default to 'pending'
            $table->timestamps();
            $table->softDeletes(); // Add soft delete column to mark records as deleted without removing them

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayment_schedules');
    }
};
