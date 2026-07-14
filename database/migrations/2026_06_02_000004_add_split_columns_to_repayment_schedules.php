<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            // Who owns this schedule row
            $table->foreignId('user_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('users')
                ->nullOnDelete();

            // Installment number within the loan (1, 2, 3…)
            $table->unsignedTinyInteger('installment_number')
                ->default(1)
                ->after('user_id');

            // Revenue split — critical for correct GL posting
            $table->decimal('principal_amount', 12, 2)
                ->default(0)
                ->after('emi_amount')
                ->comment('Principal portion of this installment');

            $table->decimal('interest_amount', 12, 2)
                ->default(0)
                ->after('principal_amount')
                ->comment('Interest earned this period — recognise on payment');

            $table->decimal('fee_amount', 12, 2)
                ->default(0)
                ->after('interest_amount')
                ->comment('Fee portion earned this period (initiation + service fee spread)');

            // Tracking
            $table->boolean('gl_posted')->default(false)->after('status');
            $table->timestamp('paid_at')->nullable()->after('gl_posted');

            $table->index(['loan_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'user_id', 'installment_number',
                'principal_amount', 'interest_amount', 'fee_amount',
                'gl_posted', 'paid_at',
            ]);
        });
    }
};
