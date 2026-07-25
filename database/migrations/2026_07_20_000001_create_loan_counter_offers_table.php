<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff-proposed counter-offers for applications that failed the
     * automated affordability check at submission (loan_applications.status
     * = 'affordability_review'). One application can accumulate more than
     * one row here over time (declined, then staff tries a different
     * figure), so this is its own append-only table rather than columns
     * bolted onto loan_applications.
     */
    public function up(): void
    {
        Schema::create('loan_counter_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->foreignId('loan_product_id')->constrained('loan_products');
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('months');
            $table->decimal('instalment', 12, 2);
            $table->foreignId('proposed_by')->constrained('users');
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | superseded
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['loan_application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_counter_offers');
    }
};
