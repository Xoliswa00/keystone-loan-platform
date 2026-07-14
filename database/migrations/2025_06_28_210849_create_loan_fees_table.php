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
        Schema::create('loan_fees', function (Blueprint $table) {

            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('initiation_fee', 15, 2);
            $table->decimal('service_fee', 15, 2);
            $table->decimal('total_due', 15, 2);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_fees');
    }
};
