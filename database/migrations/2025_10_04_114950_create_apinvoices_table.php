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
        Schema::create('apinvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('apvendors');
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('balance_due', 15, 2);
            $table->string('currency')->default('ZAR');
            $table->string('status')->default('unpaid'); // unpaid, partial, paid
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apinvoices');
    }
};
