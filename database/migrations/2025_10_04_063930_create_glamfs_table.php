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
        Schema::create('glamfs', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->string('account_type'); // Asset, Liability, Equity, Revenue, Expense
            $table->string('financial_statement'); // BS, P&L, Notes
            $table->string('branch_code')->nullable(); // Optional for branch-specific
            $table->string('custom_1')->nullable(); // For future mapping
            $table->string('custom_2')->nullable(); // For future mapping
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glamfs');
    }
};
