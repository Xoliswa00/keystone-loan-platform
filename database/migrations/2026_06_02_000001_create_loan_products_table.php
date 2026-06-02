<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->decimal('initiation_fee_flat', 10, 2)->default(0);    // R165 flat component
            $table->decimal('initiation_fee_rate', 6, 4)->default(0);     // 0.10 = 10% of amount > R1000
            $table->decimal('initiation_fee_cap', 10, 2)->default(0);     // NCA cap
            $table->decimal('monthly_service_fee', 10, 2)->default(69.00);// NCA max R69 + VAT
            $table->decimal('vat_rate', 5, 4)->default(0.15);             // 15% VAT on fees
            $table->boolean('requires_enhanced_affordability')->default(false);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed standard product (current business policy)
        DB::table('loan_products')->insert([
            'name'                           => 'Standard Loan',
            'code'                           => 'standard',
            'min_amount'                     => 500.00,
            'max_amount'                     => 3000.00,
            'min_months'                     => 1,
            'max_months'                     => 1,
            'monthly_interest_rate'          => 0.0500,   // 5% p/m
            'initiation_fee_flat'            => 165.00,
            'initiation_fee_rate'            => 0.10,
            'initiation_fee_cap'             => 1207.50,
            'monthly_service_fee'            => 69.00,
            'vat_rate'                       => 0.15,
            'requires_enhanced_affordability'=> false,
            'active'                         => true,
            'description'                    => 'Short-term loan payable on next salary date. NCA compliant.',
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);

        // Extended product — inactive by default, admin unlocks it
        DB::table('loan_products')->insert([
            'name'                           => 'Extended Loan',
            'code'                           => 'extended',
            'min_amount'                     => 1000.00,
            'max_amount'                     => 5000.00,
            'min_months'                     => 2,
            'max_months'                     => 3,
            'monthly_interest_rate'          => 0.0500,
            'initiation_fee_flat'            => 165.00,
            'initiation_fee_rate'            => 0.10,
            'initiation_fee_cap'             => 1207.50,
            'monthly_service_fee'            => 69.00,
            'vat_rate'                       => 0.15,
            'requires_enhanced_affordability'=> true,
            'active'                         => false,  // OFF until owners enable it
            'description'                    => 'Multi-month loan up to 3 instalments. Enhanced affordability check required.',
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
