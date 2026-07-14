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
        Schema::create('arinvoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained('customers');

            $table->date('invoice_date');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('balance_due', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, partially_paid
            $table->string('currency')->default('ZAR');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arinvoices');
    }
};
