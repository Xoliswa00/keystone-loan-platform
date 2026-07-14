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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique(); // e.g. 1000
            $table->string('account_category'); // e.g. Asset, Liability, Equity, Income, Expense
            $table->string('account_group'); // e.g. Current Asset, Operating Expense
            $table->string('account_type'); // e.g. Bank, Receivable, Payable, etc.
            $table->string('statement_section'); // e.g. Balance Sheet, Income Statement
            $table->string('note_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('custom_fields')->nullable(); // for future mapping flexibility

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
