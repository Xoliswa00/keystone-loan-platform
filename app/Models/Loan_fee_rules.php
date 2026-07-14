<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan_fee_rules extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_type',
        'applicability',
        'rate',
        'flat_fee',
        'cap',
        'months_valid',
        'loan_type',
    ];
}
