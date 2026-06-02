<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Standardised role column — replaces the dual role+rule_id system
            $table->enum('system_role', [
                'client',        // borrower — default
                'loan_officer',  // reviews and approves applications
                'finance',       // access to reports, GL, reconciliation
                'admin',         // full access (combines loan_officer + finance + settings)
                'it_admin',      // user management, system config
                'viewer',        // read-only access to reports
            ])->default('client')->after('rule_id');
        });

        // Migrate existing data: rule_id = 2 → admin, else → client
        DB::statement("UPDATE users SET system_role = CASE WHEN rule_id = 2 THEN 'admin' ELSE 'client' END");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('system_role');
        });
    }
};
