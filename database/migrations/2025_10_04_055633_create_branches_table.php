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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code')->unique(); // e.g. 01
            $table->string('branch_name');
            $table->string('location')->nullable();
            $table->enum('branch_type', ['online', 'instore'])->default('online');
            $table->boolean('is_active')->default(true);
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
