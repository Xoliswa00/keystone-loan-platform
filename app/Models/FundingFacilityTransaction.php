<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundingFacilityTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id', 'transaction_type', 'amount',
        'transaction_date', 'reference', 'gl_batch_reference',
        'gl_posted', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'gl_posted'        => 'boolean',
    ];

    const TYPE_LABELS = [
        'drawdown'         => 'Drawdown',
        'repayment'        => 'Repayment',
        'interest_payment' => 'Interest Payment',
        'interest_accrual' => 'Interest Accrual',
        'fee'              => 'Facility Fee',
        'reversal'         => 'Reversal',
    ];

    const TYPE_CLASSES = [
        'drawdown'         => 'kc-badge-navy',
        'repayment'        => 'kc-badge-green',
        'interest_payment' => 'kc-badge-gold',
        'interest_accrual' => 'kc-badge-silver',
        'fee'              => 'kc-badge-silver',
        'reversal'         => 'kc-badge-red',
    ];

    public function facility()
    {
        return $this->belongsTo(FundingFacility::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
