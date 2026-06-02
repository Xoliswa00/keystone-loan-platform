<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the Chart of Accounts and GL Mappings required for Keystone Lending.
 *
 * Account structure follows a micro-lender P&L / Balance Sheet layout
 * compliant with IFRS 9 and NCR reporting requirements.
 *
 * Account code convention:
 *   1xxx = Assets
 *   2xxx = Liabilities
 *   3xxx = Equity
 *   4xxx = Income
 *   5xxx = Expenses
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── Chart of Accounts ─────────────────────────────────────────────────
        $coa = [
            // ASSETS
            ['account_code' => '1100', 'account_category' => 'asset',     'account_group' => 'Current Asset',    'account_type' => 'bank',         'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 3'],
            ['account_code' => '1200', 'account_category' => 'asset',     'account_group' => 'Loans Receivable', 'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1210', 'account_category' => 'asset',     'account_group' => 'Loans Receivable', 'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'], // Stage 1
            ['account_code' => '1220', 'account_category' => 'asset',     'account_group' => 'Loans Receivable', 'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'], // Stage 2
            ['account_code' => '1230', 'account_category' => 'asset',     'account_group' => 'Loans Receivable', 'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'], // Stage 3
            ['account_code' => '1240', 'account_category' => 'asset',     'account_group' => 'Loans Receivable', 'account_type' => 'contra_asset', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'], // Less: Allowance for Credit Losses
            ['account_code' => '1300', 'account_category' => 'asset',     'account_group' => 'Current Asset',    'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'], // Interest Receivable
            ['account_code' => '1310', 'account_category' => 'asset',     'account_group' => 'Current Asset',    'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'], // Fee Receivable
            ['account_code' => '1320', 'account_category' => 'asset',     'account_group' => 'Current Asset',    'account_type' => 'receivable',   'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'], // Penalty Receivable
            ['account_code' => '1400', 'account_category' => 'asset',     'account_group' => 'Current Asset',    'account_type' => 'vat',          'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 6'], // VAT Input

            // LIABILITIES
            ['account_code' => '2100', 'account_category' => 'liability', 'account_group' => 'Current Liability','account_type' => 'deferred_income','statement_section' => 'Balance Sheet', 'note_reference' => 'Note 7'], // Deferred Interest Income
            ['account_code' => '2110', 'account_category' => 'liability', 'account_group' => 'Current Liability','account_type' => 'deferred_income','statement_section' => 'Balance Sheet', 'note_reference' => 'Note 7'], // Deferred Fee Income
            ['account_code' => '2200', 'account_category' => 'liability', 'account_group' => 'Current Liability','account_type' => 'vat',           'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 8'], // VAT Output
            ['account_code' => '2300', 'account_category' => 'liability', 'account_group' => 'Current Liability','account_type' => 'payable',       'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 9'], // PAYE Payable
            ['account_code' => '2400', 'account_category' => 'liability', 'account_group' => 'Current Liability','account_type' => 'payable',       'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 9'], // Income Tax Payable

            // EQUITY
            ['account_code' => '3100', 'account_category' => 'equity',    'account_group' => 'Equity',           'account_type' => 'equity',        'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 10'],
            ['account_code' => '3200', 'account_category' => 'equity',    'account_group' => 'Equity',           'account_type' => 'retained_earnings','statement_section' => 'Balance Sheet','note_reference' => 'Note 10'],

            // INCOME
            ['account_code' => '4100', 'account_category' => 'income',    'account_group' => 'Revenue',          'account_type' => 'income',        'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'], // Interest Income (VAT exempt)
            ['account_code' => '4200', 'account_category' => 'income',    'account_group' => 'Revenue',          'account_type' => 'income',        'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'], // Initiation Fee Income (excl. VAT)
            ['account_code' => '4210', 'account_category' => 'income',    'account_group' => 'Revenue',          'account_type' => 'income',        'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'], // Service Fee Income (excl. VAT)
            ['account_code' => '4300', 'account_category' => 'income',    'account_group' => 'Revenue',          'account_type' => 'income',        'statement_section' => 'Income Statement', 'note_reference' => 'Note 2'], // Penalty Income
            ['account_code' => '4400', 'account_category' => 'income',    'account_group' => 'Other Income',     'account_type' => 'income',        'statement_section' => 'Income Statement', 'note_reference' => 'Note 2'], // Sundry Income

            // EXPENSES
            ['account_code' => '5100', 'account_category' => 'expense',   'account_group' => 'Credit Loss',      'account_type' => 'expense',       'statement_section' => 'Income Statement', 'note_reference' => 'Note 11'], // Credit Loss Expense (IFRS 9 ECL)
            ['account_code' => '5200', 'account_category' => 'expense',   'account_group' => 'Operating Expense','account_type' => 'expense',       'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'], // Bank Charges (Nu-Pay fees)
            ['account_code' => '5300', 'account_category' => 'expense',   'account_group' => 'Operating Expense','account_type' => 'expense',       'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'], // Staff Costs
            ['account_code' => '5400', 'account_category' => 'expense',   'account_group' => 'Operating Expense','account_type' => 'expense',       'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'], // NCR Levies
            ['account_code' => '5500', 'account_category' => 'expense',   'account_group' => 'Operating Expense','account_type' => 'expense',       'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'], // General Operating Expenses
        ];

        $coaRows = array_map(function ($row) use ($now) {
            return array_merge($row, [
                'is_active'     => true,
                'custom_fields' => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }, $coa);

        // Skip if already seeded
        if (DB::table('chart_of_accounts')->count() === 0) {
            DB::table('chart_of_accounts')->insert($coaRows);
        }

        // ── GL Mappings ────────────────────────────────────────────────────────
        // Maps symbolic keys used in services → actual account_codes in the COA
        $mappings = [
            // Disbursement
            ['key' => 'loan_disbursement_dr',   'account_code' => '1200', 'description' => 'Loans Receivable (disbursement debit)'],
            ['key' => 'loan_disbursement_cr',   'account_code' => '1100', 'description' => 'Bank — cash out on disbursement'],

            // Deferred income (multi-month loans)
            ['key' => 'deferred_interest_cr',   'account_code' => '2100', 'description' => 'Deferred Interest Income'],
            ['key' => 'deferred_fee_cr',         'account_code' => '2110', 'description' => 'Deferred Fee Income'],

            // Recognised income
            ['key' => 'interest_income_cr',     'account_code' => '4100', 'description' => 'Interest Income'],
            ['key' => 'fee_income_cr',           'account_code' => '4200', 'description' => 'Fee Income (initiation + service, excl. VAT)'],
            ['key' => 'penalty_income',          'account_code' => '4300', 'description' => 'Penalty Income'],
            ['key' => 'penalty_receivable_dr',   'account_code' => '1320', 'description' => 'Penalty Receivable (dishonour fees owed)'],

            // Repayment
            ['key' => 'loan_repayment_dr',       'account_code' => '1100', 'description' => 'Bank — cash received on repayment'],

            // VAT
            ['key' => 'vat_output_cr',           'account_code' => '2200', 'description' => 'VAT Output (on fees)'],
            ['key' => 'vat_input_dr',            'account_code' => '1400', 'description' => 'VAT Input (on expenses)'],

            // Bad debt / ECL
            ['key' => 'credit_loss_expense',     'account_code' => '5100', 'description' => 'Credit Loss Expense — IFRS 9 ECL charge'],
            ['key' => 'allowance_credit_losses', 'account_code' => '1240', 'description' => 'Allowance for Credit Losses (contra-asset)'],

            // Bank charges
            ['key' => 'bank_charges',            'account_code' => '5200', 'description' => 'Bank Charges (Nu-Pay collection fees)'],
        ];

        foreach ($mappings as $m) {
            DB::table('glmappings')->updateOrInsert(
                ['key' => $m['key']],
                [
                    'account_code' => $m['account_code'],
                    'description'  => $m['description'],
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('glmappings')->whereIn('key', [
            'loan_disbursement_dr','loan_disbursement_cr',
            'deferred_interest_cr','deferred_fee_cr',
            'interest_income_cr','fee_income_cr','penalty_income','penalty_receivable_dr',
            'loan_repayment_dr','vat_output_cr','vat_input_dr',
            'credit_loss_expense','allowance_credit_losses','bank_charges',
        ])->delete();

        DB::table('chart_of_accounts')->whereIn('account_code', [
            '1100','1200','1210','1220','1230','1240',
            '1300','1310','1320','1400',
            '2100','2110','2200','2300','2400',
            '3100','3200',
            '4100','4200','4210','4300','4400',
            '5100','5200','5300','5400','5500',
        ])->delete();
    }
};
