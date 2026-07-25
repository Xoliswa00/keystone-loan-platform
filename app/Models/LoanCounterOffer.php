<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCounterOffer extends Model
{
    protected $fillable = [
        'loan_application_id',
        'loan_product_id',
        'amount',
        'months',
        'instalment',
        'proposed_by',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'instalment' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function proposedBy()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
