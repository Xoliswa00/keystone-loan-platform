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
        Schema::create('nupay_transactions_stagings', function (Blueprint $table) {
            $table->id();
            // Core references
            $table->unsignedBigInteger('import_id')->nullable();
            $table->string('transaction_type', 50);
            $table->string('approved')->nullable();

            // Mandate / tracking
            $table->string('mandate_id')->nullable();
            $table->string('mandate_request_tran_id')->nullable();
            $table->string('tracking')->nullable();
            $table->string('mandate_reference_number')->nullable();
            $table->string('contract_reference')->nullable();

            // Service / bank details
            $table->string('service_name')->nullable();
            $table->string('debtor_bank')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('creditor_bank')->nullable();

            // Dates
            $table->date('date_of_first_instalment')->nullable();
            $table->date('date_loaded')->nullable();
            $table->date('action_date')->nullable();
            $table->date('cycle_date')->nullable();
            $table->date('date_created')->nullable();

            // Amounts
            $table->decimal('instalment_amount', 15, 2)->nullable();
            $table->decimal('tpf_value', 15, 2)->nullable();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('insurance_amount', 15, 2)->nullable();

            // Authentication / status
            $table->string('authentication')->nullable();
            $table->string('user_reference')->nullable();
            $table->string('status')->nullable();

            // Instalments
            $table->integer('instalment')->nullable();
            $table->integer('original_instalment')->nullable();
            $table->integer('rescheduled_instalment')->nullable();
            $table->integer('total_instalments')->nullable();

            // Debtor details
            $table->string('debtor_id')->nullable();
            $table->string('debtor_name')->nullable();
            $table->string('debtor_account_number')->nullable();
            $table->string('debtor_account_type')->nullable();
            $table->string('debtor_branch_number')->nullable();
            $table->string('debtor_phone_number')->nullable();
            $table->string('debtor_email')->nullable();

            // Response / employer / merchant
            $table->dateTime('response_date_time')->nullable();
            $table->string('employer_code')->nullable();
            $table->string('merchant_number')->nullable();
            $table->string('frequency')->nullable();

            // Insurance
            $table->string('insurer')->nullable();
            $table->string('insurance_id')->nullable();

            // Flags / response codes
            $table->string('active')->nullable();
            $table->string('response_description')->nullable();
            $table->string('response_code')->nullable();
            $table->string('disputable')->nullable();
            $table->string('reason_code')->nullable();
            $table->string('reason_code_description')->nullable();

            // Raw payload (DB uses LONGTEXT, not JSON)
            $table->longText('raw_row_json')->nullable();

            // Import / posting
            $table->string('import_ref', 500)->nullable();
            $table->dateTime('posted_at')->nullable();

            // Laravel timestamps (match DB)
            $table->timestamps();

            /* =========================
               Indexes (EXACT DB MATCH)
               ========================= */
            $table->index('import_id');
            $table->index('contract_reference');
            $table->index('mandate_id');
            $table->index('action_date');
            $table->index(['contract_reference', 'action_date']);
            $table->index('status');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nupay_transactions_stagings');
    }
};
