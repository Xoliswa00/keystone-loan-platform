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
        Schema::create('arbatches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->morphs('source'); // source_type, source_id (e.g. LoanDisbursement)
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'posted', 'reversed'])->default('pending');
            $table->boolean('posted_to_gl')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arbatches');
    }
};
