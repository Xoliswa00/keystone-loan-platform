<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NCA s.72 / NCR Reg 18 dispute register. When a consumer disputes
     * information reported (or about to be reported) to a credit bureau, the
     * credit provider must investigate and must not keep reporting the
     * disputed adverse data as undisputed. AccountSnapshotService reads open
     * rows here to set reporting_account_snapshots.under_dispute, and
     * ExportProfileRunner suppresses disputed rows from any real outbound run.
     *
     * loan_id FKs loans.id (NOT the repayment_schedules.loan_id ->
     * loan_applications.id quirk).
     */
    public function up(): void
    {
        Schema::create('credit_report_disputes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users');
            $table->timestamp('raised_at');
            $table->date('respond_by')->comment('raised_at + 20 business days — NCR Reg 18 investigation window');

            $table->text('reason');
            $table->string('reference')->nullable()->comment('Free-text handle shown on the snapshot when disputed');

            $table->string('status', 20)->default('open')->comment('open | resolved');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['status', 'respond_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_report_disputes');
    }
};
