<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one recipient gets, and when. `columns` is an ordered list of
     * {source, label, format} — source must be a whitelisted column of the
     * dataset (config/exports.php), validated at save time. No expressions.
     */
    public function up(): void
    {
        Schema::create('export_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('export_recipient_id')->constrained('export_recipients')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('dataset', 40)->default('account_snapshot');
            $table->string('format', 20)->default('csv')->comment('csv | delimited (Phase 1); fixed_width | sacrra reserved');
            $table->string('delimiter', 4)->default(',');
            $table->string('enclosure', 2)->default('"');
            $table->boolean('include_header')->default(true);
            $table->string('line_ending', 4)->default('lf')->comment('lf | crlf');

            $table->json('columns')->comment('Ordered [{source,label,format}]');
            $table->json('filters')->nullable()->comment('Whitelisted field => equality/in values');

            $table->string('filename_pattern')->default('{recipient_slug}_{profile_slug}_{period}.csv');
            $table->unsignedTinyInteger('schedule_day_of_month')->default(5)->comment('1-28; runner also catches up if a day is missed');
            $table->unsignedTinyInteger('period_offset')->default(1)->comment('Months back from run date to the reporting period (1 = previous month)');

            $table->boolean('active')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['active', 'schedule_day_of_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_profiles');
    }
};
