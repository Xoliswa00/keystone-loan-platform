<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',

        'interest_rate',

        'interest_amount',

        'initiation_fee',

        'service_fee',

        'total_due',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
