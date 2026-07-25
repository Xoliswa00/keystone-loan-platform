<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * nupay_transaction's $fillable has always listed 'loan_repayment_id'
     * (with a matching loanRepayment() relation), and NuPayService::
     * postTransaction() writes it on every successful post — linking the
     * archived NuPay row to the actual LoanRepayment it created — but this
     * column never existed. Every post-to-GL attempt for every transaction
     * type (success/failed/canceled/reversed) has been failing with
     * "Unknown column 'loan_repayment_id'" at the final archive step —
     * postTransaction() wraps the whole flow in one DB::transaction(), so
     * this failure rolls back the GL entries too, not just the archive
     * write. Nothing has actually posted successfully; every attempt fails
     * cleanly (no partial/inconsistent state), but fails all the same.
     */
    public function up(): void
    {
        Schema::table('nupay_transactions', function (Blueprint $table) {
            $table->foreignId('loan_repayment_id')->nullable()->after('debtor_id')
                ->constrained('loan_repayments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nupay_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_repayment_id');
        });
    }
};
