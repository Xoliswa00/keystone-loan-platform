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
        Schema::create('glmappings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. 'loan_disbursement', 'interest_income'
            $table->string('account_code'); // links to gl_accounts.code
            $table->string('entry_type'); // 'debit' or 'credit'
            $table->string('is_active')->default('1'); // '1' for active, '0' for inactive

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glmappings');
    }
};
