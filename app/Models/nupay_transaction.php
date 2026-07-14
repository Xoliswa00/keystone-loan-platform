<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class nupay_transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_ref',
        'mandate_id',
        'debtor_id',
        'loan_repayment_id',
        'amount',
        'fee',
        'net_amount',
        'transaction_type',
        'posted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function loanRepayment()
    {
        return $this->belongsTo(LoanRepayment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'debtor_id', 'ID_Number');
    }
}
