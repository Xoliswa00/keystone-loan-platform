<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The NCA-compliant chart of accounts and GL mappings — same data
 * previously inserted by 2026_06_02_000010_seed_chart_of_accounts_and_gl_mappings.php's
 * up() method, plus the funding-facility accounts/mappings previously
 * inserted by 2026_06_02_000016_create_funding_facilities_tables.php's
 * up() method — both migrations now only hold schema. This is the real
 * chart of accounts GLPostingService and every glmappings lookup in the
 * app actually uses — do not confuse with the old, incompatible
 * ChartOfAccountsSeeder/GlmappingSeeder classes that used to exist (see the
 * comment in DatabaseSeeder.php).
 *
 * Every account_code is upserted individually (updateOrInsert), not gated
 * behind a single "table empty?" check — that guard was the actual bug the
 * first time this seeder shipped: it silently skipped seeding entirely
 * whenever any other migration or seeder had already inserted even one row
 * into chart_of_accounts first.
 */
class ChartOfAccountsAndGlMappingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $coa = [
            // ASSETS
            ['account_code' => '1100', 'account_category' => 'asset', 'account_group' => 'Current Asset', 'account_type' => 'bank', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 3'],
            ['account_code' => '1200', 'account_category' => 'asset', 'account_group' => 'Loans Receivable', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1210', 'account_category' => 'asset', 'account_group' => 'Loans Receivable', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1220', 'account_category' => 'asset', 'account_group' => 'Loans Receivable', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1230', 'account_category' => 'asset', 'account_group' => 'Loans Receivable', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1240', 'account_category' => 'asset', 'account_group' => 'Loans Receivable', 'account_type' => 'contra_asset', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 4'],
            ['account_code' => '1300', 'account_category' => 'asset', 'account_group' => 'Current Asset', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'],
            ['account_code' => '1310', 'account_category' => 'asset', 'account_group' => 'Current Asset', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'],
            ['account_code' => '1320', 'account_category' => 'asset', 'account_group' => 'Current Asset', 'account_type' => 'receivable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 5'],
            ['account_code' => '1400', 'account_category' => 'asset', 'account_group' => 'Current Asset', 'account_type' => 'vat', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 6'],

            // LIABILITIES
            ['account_code' => '2100', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'deferred_income', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 7'],
            ['account_code' => '2110', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'deferred_income', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 7'],
            ['account_code' => '2200', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'vat', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 8'],
            ['account_code' => '2300', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 9'],
            ['account_code' => '2400', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 9'],

            // EQUITY
            ['account_code' => '3100', 'account_category' => 'equity', 'account_group' => 'Equity', 'account_type' => 'equity', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 10'],
            ['account_code' => '3200', 'account_category' => 'equity', 'account_group' => 'Equity', 'account_type' => 'retained_earnings', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 10'],

            // INCOME
            ['account_code' => '4100', 'account_category' => 'income', 'account_group' => 'Revenue', 'account_type' => 'income', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'],
            ['account_code' => '4200', 'account_category' => 'income', 'account_group' => 'Revenue', 'account_type' => 'income', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'],
            ['account_code' => '4210', 'account_category' => 'income', 'account_group' => 'Revenue', 'account_type' => 'income', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 1'],
            ['account_code' => '4300', 'account_category' => 'income', 'account_group' => 'Revenue', 'account_type' => 'income', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 2'],
            ['account_code' => '4400', 'account_category' => 'income', 'account_group' => 'Other Income', 'account_type' => 'income', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 2'],

            // EXPENSES
            ['account_code' => '5100', 'account_category' => 'expense', 'account_group' => 'Credit Loss', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 11'],
            ['account_code' => '5200', 'account_category' => 'expense', 'account_group' => 'Operating Expense', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'],
            ['account_code' => '5300', 'account_category' => 'expense', 'account_group' => 'Operating Expense', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'],
            ['account_code' => '5400', 'account_category' => 'expense', 'account_group' => 'Operating Expense', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'],
            ['account_code' => '5500', 'account_category' => 'expense', 'account_group' => 'Operating Expense', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 12'],

            // FUNDING FACILITIES (Keystone's own capital liabilities — investors/banks/partners)
            ['account_code' => '2500', 'account_category' => 'liability', 'account_group' => 'Long-term Liability', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 13'],
            ['account_code' => '2510', 'account_category' => 'liability', 'account_group' => 'Short-term Liability', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 13'],
            ['account_code' => '2520', 'account_category' => 'liability', 'account_group' => 'Shareholder Loans', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 14'],
            ['account_code' => '2530', 'account_category' => 'liability', 'account_group' => 'Current Liability', 'account_type' => 'payable', 'statement_section' => 'Balance Sheet', 'note_reference' => 'Note 15'],
            ['account_code' => '5600', 'account_category' => 'expense', 'account_group' => 'Finance Cost', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 16'],
            ['account_code' => '5610', 'account_category' => 'expense', 'account_group' => 'Finance Cost', 'account_type' => 'expense', 'statement_section' => 'Income Statement', 'note_reference' => 'Note 16'],
        ];

        foreach ($coa as $row) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['account_code' => $row['account_code']],
                array_merge($row, [
                    'is_active' => true,
                    'custom_fields' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $mappings = [
            ['key' => 'loan_disbursement_dr', 'account_code' => '1200', 'entry_type' => 'debit', 'description' => 'Loans Receivable (disbursement debit)'],
            ['key' => 'loan_disbursement_cr', 'account_code' => '1100', 'entry_type' => 'credit', 'description' => 'Bank — cash out on disbursement'],
            ['key' => 'deferred_interest_cr', 'account_code' => '2100', 'entry_type' => 'credit', 'description' => 'Deferred Interest Income'],
            ['key' => 'deferred_fee_cr', 'account_code' => '2110', 'entry_type' => 'credit', 'description' => 'Deferred Fee Income'],
            ['key' => 'client_credit_balance_cr', 'account_code' => '2300', 'entry_type' => 'credit', 'description' => 'Client Credit Balance — overpayments owed back to customer'],
            ['key' => 'interest_income_cr', 'account_code' => '4100', 'entry_type' => 'credit', 'description' => 'Interest Income'],
            ['key' => 'fee_income_cr', 'account_code' => '4200', 'entry_type' => 'credit', 'description' => 'Fee Income (initiation + service, excl. VAT)'],
            ['key' => 'penalty_income', 'account_code' => '4300', 'entry_type' => 'credit', 'description' => 'Penalty Income'],
            ['key' => 'penalty_receivable_dr', 'account_code' => '1320', 'entry_type' => 'debit', 'description' => 'Penalty Receivable (dishonour fees owed)'],
            ['key' => 'loan_repayment_dr', 'account_code' => '1100', 'entry_type' => 'debit', 'description' => 'Bank — cash received on repayment'],
            ['key' => 'vat_output_cr', 'account_code' => '2200', 'entry_type' => 'credit', 'description' => 'VAT Output (on fees)'],
            ['key' => 'vat_input_dr', 'account_code' => '1400', 'entry_type' => 'debit', 'description' => 'VAT Input (on expenses)'],
            ['key' => 'credit_loss_expense', 'account_code' => '5100', 'entry_type' => 'debit', 'description' => 'Credit Loss Expense — IFRS 9 ECL charge'],
            ['key' => 'allowance_credit_losses', 'account_code' => '1240', 'entry_type' => 'credit', 'description' => 'Allowance for Credit Losses (contra-asset)'],
            ['key' => 'bank_charges', 'account_code' => '5200', 'entry_type' => 'debit', 'description' => 'Bank Charges (Nu-Pay collection fees)'],
            ['key' => 'recovery_income_cr', 'account_code' => '4400', 'entry_type' => 'credit', 'description' => 'Recovery Income (cash received on written-off accounts)'],
            ['key' => 'share_capital_cr', 'account_code' => '3100', 'entry_type' => 'credit', 'description' => 'Share Capital'],
            ['key' => 'retained_earnings_cr', 'account_code' => '3200', 'entry_type' => 'credit', 'description' => 'Retained Earnings — net profit/loss transferred at year-end'],

            // Funding facility transactions
            ['key' => 'loans_payable_cr', 'account_code' => '2500', 'entry_type' => 'credit', 'description' => 'Loans Payable — external funders (drawdown credit)'],
            ['key' => 'shareholder_loan_cr', 'account_code' => '2520', 'entry_type' => 'credit', 'description' => 'Shareholder Loans Payable'],
            ['key' => 'accrued_interest_cr', 'account_code' => '2530', 'entry_type' => 'credit', 'description' => 'Accrued Interest Payable — funding facilities'],
            ['key' => 'interest_expense_dr', 'account_code' => '5600', 'entry_type' => 'debit', 'description' => 'Interest Expense — cost of funding'],
            ['key' => 'facility_fee_dr', 'account_code' => '5610', 'entry_type' => 'debit', 'description' => 'Facility Fee / Arrangement Fee Expense'],
        ];

        foreach ($mappings as $m) {
            DB::table('glmappings')->updateOrInsert(
                ['key' => $m['key']],
                [
                    'account_code' => $m['account_code'],
                    'entry_type' => $m['entry_type'],
                    'description' => $m['description'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
