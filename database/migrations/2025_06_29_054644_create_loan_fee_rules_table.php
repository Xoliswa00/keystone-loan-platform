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
        Schema::create('loan_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('fee_type', ['interest', 'initiation', 'service', 'default']);
            $table->string('applicability'); // 'first_timer', 'loyal_returnee', 'general'
            $table->decimal('rate', 6, 3)->nullable(); // e.g. 0.05 or 0.03
            $table->decimal('flat_fee', 10, 2)->nullable(); // e.g. R60
            $table->decimal('cap', 10, 2)->nullable(); // e.g. R1050
            $table->integer('months_valid')->nullable(); // e.g. 12 (for loyalty periods)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_fee_rules');
    }
};
