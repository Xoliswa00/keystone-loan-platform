<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'transaction_type',
        'amount',
        'transaction_date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
