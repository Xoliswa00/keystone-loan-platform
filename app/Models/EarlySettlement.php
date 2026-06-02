<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarlySettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'user_id', 'settlement_date',
        'outstanding_balance', 'interest_rebate', 'fee_rebate', 'settlement_amount',
        'status', 'quote_expires_at', 'processed_by',
    ];

    protected $casts = [
        'settlement_date'     => 'date',
        'quote_expires_at'    => 'datetime',
        'outstanding_balance' => 'decimal:2',
        'interest_rebate'     => 'decimal:2',
        'fee_rebate'          => 'decimal:2',
        'settlement_amount'   => 'decimal:2',
    ];

    public function loan()  { return $this->belongsTo(Loan::class); }
    public function user()  { return $this->belongsTo(User::class); }
}
