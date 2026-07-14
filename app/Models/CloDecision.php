<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloDecision extends Model
{
    const UPDATED_AT = null; // append-only — each evaluation is a new row

    protected $fillable = [
        'loan_application_id',
        'decision',
        'risk_score',
        'compliance_status',
        'fraud_flags',
        'policy_references',
        'reason',
        'required_actions',
        'audit_required',
        'evaluated_at',
    ];

    protected $casts = [
        'fraud_flags' => 'array',
        'policy_references' => 'array',
        'required_actions' => 'array',
        'audit_required' => 'boolean',
        'evaluated_at' => 'datetime',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function scopeLatestFor($query, int $loanApplicationId)
    {
        return $query->where('loan_application_id', $loanApplicationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
