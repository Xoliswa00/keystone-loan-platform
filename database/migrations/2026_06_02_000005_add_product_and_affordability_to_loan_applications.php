<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('loan_product_id')
                ->nullable()
                ->after('loan_type')
                ->constrained('loan_products')
                ->nullOnDelete();

            $table->unsignedTinyInteger('loan_term_months')
                ->default(1)
                ->after('loan_product_id');

            // Snapshot of affordability at time of application — immutable audit record
            $table->boolean('affordability_checked')->default(false)->after('loan_term_months');
            $table->decimal('affordability_disposable_income', 12, 2)->nullable()->after('affordability_checked');
            $table->decimal('affordability_max_instalment', 12, 2)->nullable()->after('affordability_disposable_income');
            $table->decimal('affordability_instalment_requested', 12, 2)->nullable()->after('affordability_max_instalment');

            // Normalise status to lowercase string (away from mixed-case enum)
            // Existing enum: pending|approved|rejected|disbursed — adding settled, archived, deleted
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropForeign(['loan_product_id']);
            $table->dropColumn([
                'loan_product_id',
                'loan_term_months',
                'affordability_checked',
                'affordability_disposable_income',
                'affordability_max_instalment',
                'affordability_instalment_requested',
            ]);
        });
    }
};
