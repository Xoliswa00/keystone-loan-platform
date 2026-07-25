<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client-submitted proof-of-payment (for a manual payment that didn't
     * come through NuPay) sits as 'pending_review' until staff verify it —
     * that value plus 'rejected' don't fit in the VARCHAR(20) status column
     * from 2026_07_10_000003_widen_remaining_status_enums.php, so widen it
     * further while adding the verification columns.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->string('proof_of_payment_path')->nullable()->after('notes');
            $table->foreignId('submitted_by')->nullable()->after('proof_of_payment_path')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->after('submitted_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('rejection_reason')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'proof_of_payment_path', 'submitted_by', 'verified_by', 'verified_at', 'rejection_reason',
            ]);
        });

        DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
};
