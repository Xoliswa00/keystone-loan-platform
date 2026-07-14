<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                        // "Standard Loan"
            $table->string('code')->unique();                              // "standard"
            $table->decimal('min_amount', 10, 2);                         // R500
            $table->decimal('max_amount', 10, 2);                         // R3000
            $table->unsignedTinyInteger('min_months')->default(1);
            $table->unsignedTinyInteger('max_months')->default(1);
            $table->decimal('monthly_interest_rate', 6, 4);               // 0.0500 = 5% p/m (NCA max for short-term)
            $table->decimal('initiation_fee_flat', 10, 2)->default(0);    // R150 flat component (NCA cap)
            $table->decimal('initiation_fee_rate', 6, 4)->default(0);     // 0.10 = 10% of amount > R1000
            $table->decimal('initiation_fee_cap', 10, 2)->default(0);     // NCA cap
            $table->decimal('monthly_service_fee', 10, 2)->default(60.00); // NCA max R60 + VAT
            $table->decimal('vat_rate', 5, 4)->default(0.15);             // 15% VAT on fees
            $table->boolean('requires_enhanced_affordability')->default(false);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed data (Standard Loan / Extended Loan) lives in
        // database/seeders/LoanProductsSeeder.php, not here.
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
