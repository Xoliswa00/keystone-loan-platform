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
        Schema::create('nupay_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 50); // sheet name
            $table->string('import_ref')->nullable();
            $table->string('mandate_id')->nullable();
            $table->string('mandate_request_tran_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('debtor_id')->nullable();

            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nupay_transactions');
    }
};
