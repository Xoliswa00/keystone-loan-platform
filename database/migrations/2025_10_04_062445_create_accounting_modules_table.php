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
        Schema::create('accounting_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 10)->unique(); // e.g. AR, AP, CB, GL
            $table->string('module_name'); // e.g. Accounts Receivable
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_modules');
    }
};
