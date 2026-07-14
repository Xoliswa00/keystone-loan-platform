<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NcaAgreement extends Model
{
    use HasFactory;

    protected $table = 'nca_agreements';

    protected $fillable = [
        'loan_application_id', 'user_id', 'reference', 'document_type',
        'file_path', 'generated_at', 'sent_at', 'accepted_at', 'acceptance_method',
        'principal_amount', 'initiation_fee', 'initiation_fee_vat',
        'monthly_service_fee', 'monthly_service_fee_vat',
        'monthly_interest', 'total_cost_of_credit', 'annual_percentage_rate',
        'term_months', 'first_payment_date', 'created_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime', 'sent_at' => 'datetime', 'accepted_at' => 'datetime',
        'first_payment_date' => 'date',
        'principal_amount' => 'decimal:2',
        'initiation_fee' => 'decimal:2',
        'initiation_fee_vat' => 'decimal:2',
        'monthly_service_fee' => 'decimal:2',
        'monthly_service_fee_vat' => 'decimal:2',
        'monthly_interest' => 'decimal:2',
        'total_cost_of_credit' => 'decimal:2',
        'annual_percentage_rate' => 'decimal:4',
    ];

    const TYPES = [
        'pre_agreement_statement' => 'Pre-Agreement Statement (NCA s.92)',
        'loan_agreement' => 'Credit Agreement (NCA s.93)',
        'settlement_quote' => 'Settlement Quote (NCA s.125)',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? $this->document_type;
    }

    public function isSigned(): bool
    {
        return ! is_null($this->accepted_at);
    }
}
