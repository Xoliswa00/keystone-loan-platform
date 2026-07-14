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
            $table->foreignId('rule_id')
                ->nullable()
                ->after('id')
                ->constrained('rules')
                ->nullOnDelete(); // If a rule is deleted, keep user but null rule_id
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rule_id']);
            $table->dropColumn('rule_id');
        });
    }
};
