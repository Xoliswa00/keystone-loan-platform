<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound data-export engine
    |--------------------------------------------------------------------------
    |
    | Two independent kill-switches, both OFF by default. They gate two
    | distinct processing operations that must each be in the POPIA
    | records-of-processing / retention schedule before being switched on
    | in production (see docs/sacrra-integration-requirements.md, Section 1).
    |
    |   snapshots_enabled — allows keystone:build-account-snapshots to
    |     materialise the reporting_account_snapshots table (full-book
    |     borrower identity + delinquency). This is NEW PII processing even
    |     if nothing is ever exported, so it has its own flag.
    |
    |   enabled — allows keystone:run-data-exports to build files from a
    |     profile and hand them to a transport. Requires snapshots_enabled
    |     as well (there is nothing to export without a snapshot).
    |
    */

    'snapshots_enabled' => (bool) env('DATA_EXPORTS_SNAPSHOTS_ENABLED', false),
    'enabled' => (bool) env('DATA_EXPORTS_ENABLED', false),

    /*
    | Delinquency threshold (days past due) at which a loan is treated as
    | having entered default for the purpose of freezing first_default_date /
    | principal_at_default on the snapshot. Kept separate from the IFRS 9
    | stage-3 DPD (LendingSetting) so a compliance change to one does not
    | silently move the other.
    */
    'default_dpd_threshold' => (int) env('DATA_EXPORTS_DEFAULT_DPD', 90),

    /*
    | Hard ceiling on rows a single export run may emit. A profile that would
    | exceed this fails the run rather than shipping a truncated file.
    */
    'max_rows_per_run' => (int) env('DATA_EXPORTS_MAX_ROWS', 250000),

    /*
    | Disk + directory for generated export files and snapshot working files.
    | Kept on a named disk (not the catch-all 'local' root) so a retention
    | prune can target it without touching unrelated storage.
    */
    'disk' => env('DATA_EXPORTS_DISK', 'local'),
    'path' => 'data_exports',

    /*
    | Filename pattern tokens permitted in export_profiles.filename_pattern.
    | Anything else in the pattern is rejected at save time. No path
    | separators are ever allowed in a resolved filename.
    */
    'filename_tokens' => ['recipient_slug', 'profile_slug', 'period', 'Ymd', 'Ymd_His'],

    /*
    | Allow-list of destination email domains. EmailTransport refuses any
    | recipient address whose domain is not listed. Empty list = email
    | transport is unusable (the Phase-1 default — the only dataset is PII
    | and must not leave over cleartext SMTP).
    */
    'email_domain_allowlist' => array_filter(
        array_map('trim', explode(',', (string) env('DATA_EXPORTS_EMAIL_DOMAINS', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Dataset registry
    |--------------------------------------------------------------------------
    |
    | Each dataset a profile can draw from. `columns` is the ONLY set of
    | sources a profile may select — anything not listed here is rejected at
    | save time, which is the security boundary of the whole "pick columns,
    | no expressions" design.
    |
    |   type    — how ColumnFormatter should render it by default
    |   pii     — true = carries borrower personal information; a profile on
    |             a recipient of kind 'bureau' is the only place these may go,
    |             and such a profile may never use the email transport
    |   internal — true = internal accounting / risk figure that must never
    |             reach an external recipient of any kind
    |
    */

    'datasets' => [

        'account_snapshot' => [
            'model' => \App\Models\AccountSnapshot::class,
            'label' => 'Account snapshot (monthly, per loan)',
            'filterable' => ['period', 'account_status', 'under_dispute', 'nca_credit_type'],
            'columns' => [
                'period' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'account_no' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'loan_id' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'borrower_name' => ['type' => 'string', 'pii' => true, 'internal' => false],
                'id_number' => ['type' => 'string', 'pii' => true, 'internal' => false],
                'date_of_birth' => ['type' => 'date:Y-m-d', 'pii' => true, 'internal' => false],
                'gender' => ['type' => 'string', 'pii' => true, 'internal' => false],
                'nationality' => ['type' => 'string', 'pii' => true, 'internal' => true],
                'borrower_age' => ['type' => 'integer', 'pii' => true, 'internal' => false],
                'address_raw' => ['type' => 'string', 'pii' => true, 'internal' => true],
                'phone' => ['type' => 'string', 'pii' => true, 'internal' => false],
                'email' => ['type' => 'string', 'pii' => true, 'internal' => false],
                'nca_credit_type' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'ncr_purpose_code' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'loan_type' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'product_code' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'term_months' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'principal_amount' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'total_interest' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'total_fees' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'total_vat' => ['type' => 'money', 'pii' => false, 'internal' => true],
                'total_amount_due' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'instalment_amount' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'opening_balance' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'current_balance' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'arrears_amount' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'last_payment_amount' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'write_off_amount' => ['type' => 'money', 'pii' => false, 'internal' => true],
                'carried_forward_shortfall' => ['type' => 'money', 'pii' => false, 'internal' => true],
                'disbursed_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'first_instalment_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'final_instalment_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'last_payment_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'settled_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'written_off_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'first_default_date' => ['type' => 'date:Y-m-d', 'pii' => false, 'internal' => false],
                'principal_at_default' => ['type' => 'money', 'pii' => false, 'internal' => false],
                'days_past_due' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'instalments_in_arrears' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'months_in_arrears' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'ifrs9_stage' => ['type' => 'integer', 'pii' => false, 'internal' => true],
                'payment_status_code' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'payment_profile_24m' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'account_status' => ['type' => 'string', 'pii' => false, 'internal' => false],
                'loan_status_raw' => ['type' => 'string', 'pii' => false, 'internal' => true],
                'under_dispute' => ['type' => 'integer', 'pii' => false, 'internal' => false],
                'dispute_reference' => ['type' => 'string', 'pii' => false, 'internal' => false],
            ],
        ],

    ],

];
