<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLO decision log — one row per evaluation (append-only, like audit_logs).
     * Advisory only: nothing in this table changes loan_applications/loans status.
     */
    public function up(): void
    {
        Schema::create('clo_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->string('decision'); // APPROVE | REJECT | ESCALATE | REVIEW
            $table->unsignedTinyInteger('risk_score');
            $table->string('compliance_status'); // PASS | FAIL
            $table->json('fraud_flags');
            $table->json('policy_references');
            $table->text('reason')->nullable();
            $table->json('required_actions');
            $table->boolean('audit_required')->default(true);
            $table->timestamp('evaluated_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['loan_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clo_decisions');
    }
};
