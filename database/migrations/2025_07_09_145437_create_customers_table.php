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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('customer_code')->nullable()->unique(); // e.g. CLH23-880101
            $table->string('customer_type')->default('individual'); // 'individual' or 'business'
            $table->string('payment_terms')->nullable(); // e.g. '30 days', 'COD'
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->boolean('active')->default(true);

            // AR Financials
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
