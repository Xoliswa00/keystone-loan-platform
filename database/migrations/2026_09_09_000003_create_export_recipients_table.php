<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An organisation that receives an outbound data feed — a credit bureau
     * (SACRRA route), a funder, an auditor. Defining WHERE data goes is a
     * governance action: recipient create/edit is gated role:admin only,
     * separately from running or downloading an export.
     */
    public function up(): void
    {
        Schema::create('export_recipients', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 20)->default('partner')->comment('bureau | partner | internal — drives which columns a profile may select');

            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->string('transport', 20)->default('download')->comment('download | email (Phase 1); sftp | api reserved for Phase 2');
            $table->json('email_recipients')->nullable()->comment('Destination addresses for the email transport');

            $table->text('lawful_basis')->nullable()->comment('POPIA lawful basis for sharing with this recipient — required before active = true');
            $table->boolean('active')->default(false);
            $table->text('notes')->nullable();

            // ── Phase 2 (nullable, unused in Phase 1) ──
            $table->string('sftp_host')->nullable();
            $table->unsignedSmallInteger('sftp_port')->nullable();
            $table->string('sftp_username')->nullable();
            $table->string('sftp_remote_path')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->string('credential_env_key')->nullable()->comment('Name of the .env var holding the transport secret (hybrid model — no secret in the DB)');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_recipients');
    }
};
