<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_holder_name',
        'bank_name',
        'account_number',
        'account_type',
        'branch_code',
        'iban',
        'payment_method',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
