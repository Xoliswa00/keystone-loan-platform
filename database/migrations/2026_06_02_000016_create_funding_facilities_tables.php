<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funding Facilities — Keystone's own capital liabilities.
 *
 * These are the investors, banks, or partners who lend money TO the business
 * so the business can on-lend to clients. Distinct from AP (trade suppliers).
 *
 * Balance sheet treatment:
 *   Drawdown: Dr Bank / Cr Loans Payable (2500)
 *   Interest accrual: Dr Interest Expense (5600) / Cr Accrued Interest Payable (2530)
 *   Interest payment: Dr Accrued Interest Payable (2530) / Cr Bank
 *   Repayment: Dr Loans Payable (2500) / Cr Bank
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Funding Facilities (master record per funder) ─────────────────────
        Schema::create('funding_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code')->unique();          // e.g. FF-001
            $table->string('facility_name');                    // "Founder Loan — X Masuku"
            $table->enum('funder_type', [
                'shareholder_loan',   // director/founder lending to company
                'bank_credit_line',   // bank overdraft or credit facility
                'private_investor',   // external private individual
                'family_office',      // family office / HNW investor
                'other',
            ])->default('private_investor');
            $table->string('funder_name');                      // legal name of funder
            $table->string('funder_id_or_reg')->nullable();     // SA ID or company reg number
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();

            // Financial terms
            $table->decimal('facility_limit', 15, 2);           // max commitment (credit limit)
            $table->decimal('drawn_amount', 15, 2)->default(0); // total drawn down to date
            $table->decimal('current_balance', 15, 2)->default(0); // outstanding owed to funder
            $table->decimal('interest_rate', 6, 4)->default(0); // annual rate (e.g. 0.1200 = 12% p.a.)
            $table->enum('interest_type', ['fixed', 'variable', 'prime_plus'])->default('fixed');
            $table->decimal('prime_margin', 5, 4)->nullable();  // for prime_plus: +/- X%
            $table->decimal('accrued_interest', 12, 2)->default(0); // interest accrued but not yet paid

            // Dates & terms
            $table->date('facility_start_date')->nullable();
            $table->date('maturity_date')->nullable();          // null = revolving / no fixed end
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'bi-annual', 'annual', 'on_demand'])
                ->default('monthly');
            $table->integer('payment_day')->nullable();         // day of month interest is due

            // Status & management
            $table->enum('status', ['active', 'paused', 'fully_repaid', 'in_default', 'closed'])
                ->default('active');
            $table->text('collateral_description')->nullable();
            $table->text('notes')->nullable();
            $table->string('agreement_document_path')->nullable(); // uploaded facility agreement

            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'payment_day']);
        });

        // ── Facility transactions (drawdowns, repayments, interest payments) ──
        Schema::create('funding_facility_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('funding_facilities')->onDelete('cascade');
            $table->enum('transaction_type', [
                'drawdown',           // money received from funder → Dr Bank / Cr Loans Payable
                'repayment',          // principal paid back     → Dr Loans Payable / Cr Bank
                'interest_payment',   // interest paid           → Dr Accrued Interest / Cr Bank
                'interest_accrual',   // month-end accrual       → Dr Interest Expense / Cr Accrued Interest
                'fee',                // facility fee / arrangement fee
                'reversal',           // correction
            ]);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->string('gl_batch_reference')->nullable();
            $table->boolean('gl_posted')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['facility_id', 'transaction_date']);
            $table->index('transaction_type');
        });

        // The extra COA entries (2500-2530, 5600-5610) and GL mappings for
        // funding-facility transactions that used to be seeded here now live
        // in database/seeders/ChartOfAccountsAndGlMappingsSeeder.php,
        // alongside the rest of the chart of accounts, not split across
        // migrations.
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_facility_transactions');
        Schema::dropIfExists('funding_facilities');
    }
};
