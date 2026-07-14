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
        Schema::create('arpayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('arinvoices');
            $table->foreignId('customer_id')->constrained('customers');
            $table->decimal('amount_paid', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method'); // cash, card, bank transfer
            $table->string('reference')->nullable(); // e.g., bank ref or receipt

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arpayments');
    }
};
