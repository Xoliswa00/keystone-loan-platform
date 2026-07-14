<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the companies table with all fields needed for:
 *  - NCA agreements (registered name, NCR number, VAT number)
 *  - NCR compliance (credit provider category, registration)
 *  - Email notifications (from name/address)
 *  - PDFs (logo path, physical address)
 *
 * The companies table is used as a SINGLETON — one row per deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Legal identity
            $table->string('registration_no')->nullable()->after('name')
                ->comment('Company registration number (CIPC)');
            $table->string('ncr_number')->nullable()->after('registration_no')
                ->comment('NCR credit provider registration e.g. NCRCP1234');
            $table->string('vat_number')->nullable()->after('ncr_number')
                ->comment('SARS VAT registration number');
            $table->string('ncr_credit_category')->nullable()->after('vat_number')
                ->comment('NCR credit provider category: developmental / mortgage / unsecured / short_term');

            // Physical presence
            $table->text('physical_address')->nullable()->after('address');
            $table->string('postal_address')->nullable()->after('physical_address');
            $table->string('city')->nullable()->after('postal_address');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code', 10)->nullable()->after('province');
            $table->string('country', 3)->default('ZAF')->after('postal_code');

            // Brand
            $table->string('logo_path')->nullable()->after('website')
                ->comment('Path to company logo — used on PDFs and emails');
            $table->string('tagline')->nullable()->after('logo_path');
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->string('support_email')->nullable()->after('email');

            // For agreements — signatories
            $table->string('authorised_signatory')->nullable()
                ->comment('Full name of the person who signs agreements on behalf of company');
            $table->string('signatory_title')->nullable();

            // Notification preferences
            $table->string('notification_from_email')->nullable();
            $table->string('notification_from_name')->nullable();
            $table->text('notification_cc')->nullable()
                ->comment('Comma-separated CC addresses for all loan notification emails');
        });

        // Seed data (the singleton company row) lives in
        // database/seeders/CompaniesSeeder.php, not here.
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'registration_no', 'ncr_number', 'vat_number', 'ncr_credit_category',
                'physical_address', 'postal_address', 'city', 'province', 'postal_code', 'country',
                'logo_path', 'tagline', 'whatsapp_number', 'support_email',
                'authorised_signatory', 'signatory_title',
                'notification_from_email', 'notification_from_name', 'notification_cc',
            ]);
        });
    }
};
