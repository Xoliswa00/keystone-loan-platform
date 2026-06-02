<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Debt recovery tracking for written-off and NPL (90+ DPD) accounts.
         *
         * Flow:
         *  Loan written-off → recovery_case created (status: open)
         *    → contact attempts logged in admin_notes (type: collections)
         *    → partial/full payment received → recovery payment recorded
         *    → GL: Dr Bank / Cr Recovery Income (separate from normal interest income)
         *    → if fully recovered → case status: recovered
         *    → if no further recovery possible → case status: closed
         */
        Schema::create('debt_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('status', [
                'open',        // active recovery — still chasing
                'partial',     // some payments received
                'recovered',   // fully recovered
                'closed',      // no further action — written-off permanently
                'legal',       // referred to legal / external collections agency
            ])->default('open');

            $table->decimal('original_write_off_amount', 12, 2);
            $table->decimal('total_recovered', 12, 2)->default(0);
            $table->decimal('outstanding_recovery', 12, 2);    // = original - recovered

            // Case management
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_agency')->nullable();       // name of external collection agency
            $table->string('legal_reference')->nullable();       // court case / attorney reference
            $table->date('next_action_date')->nullable();
            $table->text('notes')->nullable();

            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->index(['status', 'next_action_date']);
            $table->index('user_id');
        });

        // Individual recovery payments against a recovery case
        Schema::create('debt_recovery_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_recovery_id')->constrained('debt_recoveries')->onDelete('cascade');
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method');                    // nupay | eft | cash | legal_settlement
            $table->string('payment_reference')->nullable();
            $table->string('gl_batch_reference')->nullable();    // reference to GL posting

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_recovery_payments');
        Schema::dropIfExists('debt_recoveries');
    }
};
