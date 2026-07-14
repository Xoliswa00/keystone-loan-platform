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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('address');
            $table->text('phone');
            $table->enum('role', ['admin', 'borrower', 'master'])->default('borrower');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('ID_Number'); // TODO:fix id unique feature
            $table->enum('employment_status', ['Full-time', 'Part-time', 'Self-employed', 'Unemployed']);
            $table->enum('salary_frequency', ['Weekly', 'Bi-weekly', 'Monthly'])->nullable();
            $table->decimal('net_salary', 10, 2);
            $table->integer('salary_payment_day');
            $table->integer('credit_score')->nullable();
            $table->text('ID_copy');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
