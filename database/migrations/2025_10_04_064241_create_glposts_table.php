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
        Schema::create('glposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('glentries');
            $table->foreignId('account_id')->constrained('glamfs');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->date('post_date');
            $table->string('reference')->nullable(); // invoice number, payment ref, etc.
            $table->string('module'); // AR, AP, CB, Payroll
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glposts');
    }
};
