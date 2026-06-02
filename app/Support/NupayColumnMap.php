<?php

namespace App\Support;

class NupayColumnMap
{
    /**
     * Maps NuPay Excel headers → database column names.
     * Must reflect the canonical NuPay export schema.
     */
    public static function map(): array
    {
        return [
            'Approved'                    => 'approved',
            'Mandate ID'                  => 'mandate_id',
            'Mandate Request Tran ID'     => 'mandate_request_tran_id',
            'Tracking'                    => 'tracking',
            'Mandate Reference Number'    => 'mandate_reference_number',
            'Contract Reference'          => 'contract_reference',
            'Service Name'                => 'service_name',
            'Debtor Bank'                 => 'debtor_bank',
            'Client Reference'            => 'client_reference',
            'Date of First Instalment'    => 'date_of_first_instalment',
            'Date Loaded'                 => 'date_loaded',
            'Creditor Bank'               => 'creditor_bank',
            'Instalment Amount'           => 'instalment_amount',
            'Action Date'                 => 'action_date',
            'Cycle Date'                  => 'cycle_date',
            'Authentication'              => 'authentication',
            'User Reference'              => 'user_reference',
            'Status'                      => 'status',
            'Date Created'                => 'date_created',
            'Instalment'                  => 'instalment',
            'Original Instalment'         => 'original_instalment',
            'Rescheduled Instalment'      => 'rescheduled_instalment',
            'Total Instalments'           => 'total_instalments',
            'Debtor ID'                   => 'debtor_id',
            'Debtor Name'                 => 'debtor_name',
            'Debtor Account Number'       => 'debtor_account_number',
            'Debtor Account Type'         => 'debtor_account_type',
            'Debtor Branch Number'        => 'debtor_branch_number',
            'Debtor Phone Number'         => 'debtor_phone_number',
            'Debtor Email'                => 'debtor_email',
            'Response Date & Time'        => 'response_date_time',
            'Employer Code'               => 'employer_code',
            'Merchant Number'             => 'merchant_number',
            'Frequency'                   => 'frequency',
            'TPF Value'                   => 'tpf_value',
            'Original Amount'             => 'original_amount',
            'Total Amount'                => 'total_amount',
            'Insurance Amount'            => 'insurance_amount',
            'Insurer'                     => 'insurer',
            'Insurance ID'                => 'insurance_id',
            'Active'                      => 'active',
            'Response Description'        => 'response_description',
            'response_code'               => 'response_code',
            'Disputable'                  => 'disputable',
            'Reason code'                 => 'reason_code',
            'Reason code description'     => 'reason_code_description',
        ];
    }

    public static function expectedHeaders(): array
    {
        return array_keys(self::map());
    }
}
