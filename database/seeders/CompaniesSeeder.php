<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompaniesSeeder extends Seeder
{
    /**
     * Seed the singleton company row — same data previously inserted by
     * 2026_06_02_000020_add_settings_to_companies.php's up() method, now
     * that migration only holds schema (see database/schema/mysql-schema.sql).
     */
    public function run(): void
    {
        if (Company::count() > 0) {
            return;
        }

        Company::create([
            'name' => 'Keystone Capital Partners',
            'registration_no' => '',
            'ncr_number' => '',
            'vat_number' => '',
            'ncr_credit_category' => 'unsecured',
            'address' => '',
            'physical_address' => '',
            'phone' => '',
            'whatsapp_number' => '27721853349',
            'email' => 'info@keystonecapitalpartners.co.za',
            'support_email' => 'support@keystonecapitalpartners.co.za',
            'website' => 'https://keystonecapitalpartners.co.za',
            'tagline' => 'Capital. Partnership. Growth.',
            'authorised_signatory' => '',
            'signatory_title' => 'Director',
            'notification_from_email' => 'noreply@keystonecapitalpartners.co.za',
            'notification_from_name' => 'Keystone Capital Partners',
            'notification_cc' => '',
        ]);
    }
}
