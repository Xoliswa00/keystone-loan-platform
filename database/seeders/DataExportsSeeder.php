<?php

namespace Database\Seeders;

use App\Models\ExportProfile;
use App\Models\ExportRecipient;
use Illuminate\Database\Seeder;

/**
 * A disabled, illustrative SACRRA recipient + profile so the admin screens
 * have something to look at in local / staging. Never seeds in production and
 * never carries a real email address. Not wired into DatabaseSeeder — run it
 * explicitly: php artisan db:seed --class=DataExportsSeeder
 */
class DataExportsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DataExportsSeeder is skipped in production.');

            return;
        }

        $recipient = ExportRecipient::updateOrCreate(
            ['slug' => 'sacrra-bureau-tbd'],
            [
                'name' => 'SACRRA (bureau TBD)',
                'kind' => 'bureau',
                'transport' => 'download',
                'contact_person' => 'To be confirmed once a bureau is signed',
                'lawful_basis' => null, // deliberately unset — cannot be activated
                'active' => false,
                'notes' => 'Example only. Real bureau, data-supply agreement, file spec and POPIA sign-off are prerequisites (see PR #7 requirements docs).',
            ]
        );

        ExportProfile::updateOrCreate(
            ['slug' => 'sacrra-monthly-example'],
            [
                'export_recipient_id' => $recipient->id,
                'name' => 'SACRRA monthly (example)',
                'dataset' => 'account_snapshot',
                'format' => 'csv',
                'delimiter' => ',',
                'enclosure' => '"',
                'include_header' => true,
                'line_ending' => 'crlf',
                'columns' => [
                    ['source' => 'account_no', 'label' => 'AccountNo', 'format' => 'string'],
                    ['source' => 'id_number', 'label' => 'IDNumber', 'format' => 'string'],
                    ['source' => 'borrower_name', 'label' => 'ConsumerName', 'format' => 'string'],
                    ['source' => 'nca_credit_type', 'label' => 'AccountType', 'format' => 'string'],
                    ['source' => 'disbursed_date', 'label' => 'OpenedDate', 'format' => 'date:Ymd'],
                    ['source' => 'principal_amount', 'label' => 'OpeningBalance', 'format' => 'money'],
                    ['source' => 'current_balance', 'label' => 'CurrentBalance', 'format' => 'money'],
                    ['source' => 'instalment_amount', 'label' => 'InstalmentAmount', 'format' => 'money'],
                    ['source' => 'arrears_amount', 'label' => 'ArrearsAmount', 'format' => 'money'],
                    ['source' => 'months_in_arrears', 'label' => 'MonthsInArrears', 'format' => 'integer'],
                    ['source' => 'payment_status_code', 'label' => 'PaymentStatus', 'format' => 'string'],
                    ['source' => 'account_status', 'label' => 'AccountStatus', 'format' => 'string'],
                    ['source' => 'first_default_date', 'label' => 'FirstDefaultDate', 'format' => 'date:Ymd'],
                ],
                'filename_pattern' => 'KCP_{recipient_slug}_{period}_{Ymd}.csv',
                'schedule_day_of_month' => 5,
                'period_offset' => 1,
                'active' => false,
            ]
        );

        $this->command?->info('Seeded disabled SACRRA example recipient + profile.');
    }
}
