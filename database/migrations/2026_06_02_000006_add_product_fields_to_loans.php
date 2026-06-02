<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('loan_product_id')
                ->nullable()
                ->after('loan_application_id')
                ->constrained('loan_products')
                ->nullOnDelete();

            // Immutable financial breakdown set at approval — never recalculated
            $table->decimal('principal_amount', 12, 2)->nullable()->after('loan_amount');
            $table->decimal('total_interest', 12, 2)->default(0)->after('principal_amount');
            $table->decimal('total_fees_excl_vat', 12, 2)->default(0)->after('total_interest');
            $table->decimal('total_vat', 12, 2)->default(0)->after('total_fees_excl_vat');
            $table->decimal('total_amount_due', 12, 2)->default(0)->after('total_vat');

            // Deferred income — reduced each time an installment is collected
            $table->decimal('deferred_interest', 12, 2)->default(0)->after('total_amount_due');
            $table->decimal('deferred_fees', 12, 2)->default(0)->after('deferred_interest');

            // Term stored here as source-of-truth (not just on the application)
            $table->unsignedTinyInteger('loan_term_months')->default(1)->after('loan_term');

            // Status normalised: pending|approved|disbursed|settled|rejected|archived
            // Change existing enum to string so we can add new statuses without migrations
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['loan_product_id']);
            $table->dropColumn([
                'loan_product_id',
                'principal_amount',
                'total_interest',
                'total_fees_excl_vat',
                'total_vat',
                'total_amount_due',
                'deferred_interest',
                'deferred_fees',
                'loan_term_months',
            ]);
        });
    }
};
