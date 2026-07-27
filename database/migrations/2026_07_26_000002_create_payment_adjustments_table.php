<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per shortfall/credit *event* (not one row per customer) — keeps
 * FIFO consumption trivial (orderBy created_at) and keeps the audit trail
 * granular: which specific instalment caused which specific adjustment.
 * Created by PaymentAdjustmentService whenever ManualPaymentService/
 * NuPayService accept a payment that's short or over by up to
 * LendingSetting::payment_tolerance_pct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('source_loan_id')->constrained('loans');
            $table->foreignId('source_repayment_schedule_id')->constrained('repayment_schedules');
            $table->foreignId('source_loan_repayment_id')->constrained('loan_repayments');
            $table->enum('type', ['shortfall', 'credit']);
            $table->decimal('original_amount', 10, 2)->comment('Face value at creation — never changes, for disclosure documents');
            $table->decimal('outstanding_amount', 10, 2)->comment('Shrinks as consumed/rolled/settled; 0 once fully resolved');
            $table->enum('status', ['outstanding', 'partially_applied', 'applied', 'rolled_into_loan', 'settled'])->default('outstanding');
            $table->foreignId('applied_to_loan_id')->nullable()->constrained('loans')->comment('Set once a shortfall is disclosed on a new loan, or a credit is drawn down');
            $table->foreignId('applied_to_schedule_id')->nullable()->constrained('repayment_schedules')->comment('Set when a credit is consumed against a specific future instalment');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->comment('Null for system-generated (payment-time) rows');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_adjustments');
    }
};
