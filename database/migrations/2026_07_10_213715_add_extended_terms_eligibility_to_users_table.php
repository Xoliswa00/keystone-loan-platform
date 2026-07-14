<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin-granted override — allows this client to apply for
            // extended-term (>1 month) products even when those products
            // are inactive for the general client base.
            $table->boolean('extended_terms_eligible')->default(false)->after('restricted_by')
                ->comment('Admin-granted override — allows extended-term loan applications even when the product is globally inactive');
            $table->timestamp('extended_terms_granted_at')->nullable()->after('extended_terms_eligible');
            $table->foreignId('extended_terms_granted_by')->nullable()->after('extended_terms_granted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['extended_terms_granted_by']);
            $table->dropColumn([
                'extended_terms_eligible', 'extended_terms_granted_at', 'extended_terms_granted_by',
            ]);
        });
    }
};
