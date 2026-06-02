<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // ── Employment ──
            $table->string('employer_name')->nullable();
            $table->string('employer_phone')->nullable();
            $table->enum('employment_type', ['permanent', 'contract', 'self_employed', 'unemployed'])->nullable();
            $table->enum('employment_tenure', ['less_than_6m', '6m_to_1y', '1y_to_3y', 'over_3y'])->nullable();

            // ── Income ──
            $table->decimal('gross_monthly_income', 12, 2)->nullable();
            $table->decimal('net_monthly_income', 12, 2)->nullable();
            $table->decimal('other_income', 12, 2)->default(0.00);
            $table->string('other_income_source')->nullable();

            // ── Monthly Expenses (5 slider categories) ──
            $table->decimal('expense_housing', 12, 2)->default(0.00);      // rent / bond
            $table->decimal('expense_transport', 12, 2)->default(0.00);    // car instalment / taxi / fuel
            $table->decimal('expense_existing_debt', 12, 2)->default(0.00);// other active loan repayments
            $table->decimal('expense_insurance', 12, 2)->default(0.00);    // car / life / home / medical aid
            $table->decimal('expense_living', 12, 2)->default(0.00);       // food, utilities, phone

            // ── Personal ──
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->unsignedTinyInteger('dependants')->default(0);

            // ── Derived / Audit fields (recalculated on every save) ──
            $table->decimal('total_monthly_expenses', 12, 2)->nullable();
            $table->decimal('disposable_income', 12, 2)->nullable();
            $table->decimal('max_monthly_instalment', 12, 2)->nullable();  // disposable × 30%

            // ── Completeness ──
            $table->boolean('profile_complete')->default(false);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
