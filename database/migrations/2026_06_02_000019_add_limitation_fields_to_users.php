<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // NCR compliance — waiting period after rejection
            $table->timestamp('last_rejected_at')->nullable()->after('blacklisted_at')
                ->comment('Timestamp of most recent loan rejection — used for NCR cooldown period');

            // Manual restriction by admin (beyond blacklist)
            $table->boolean('applications_restricted')->default(false)->after('last_rejected_at')
                ->comment('Admin-set restriction flag — prevents new applications regardless of other criteria');
            $table->string('restriction_reason')->nullable()->after('applications_restricted');
            $table->timestamp('restriction_expires_at')->nullable()->after('restriction_reason')
                ->comment('If set, restriction auto-lifts at this time');
            $table->foreignId('restricted_by')->nullable()->after('restriction_expires_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restricted_by']);
            $table->dropColumn([
                'last_rejected_at', 'applications_restricted',
                'restriction_reason', 'restriction_expires_at', 'restricted_by',
            ]);
        });
    }
};
