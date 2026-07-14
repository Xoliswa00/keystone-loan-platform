<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cashbook_accounts extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'bank_name',
        'account_number',
        'branch_code',
        'currency',
        'gl_account_id',
        'is_active',
    ];

    public function glAccount()
    {
        return $this->belongsTo(gl_accounts::class);
    }

    public function transactions()
    {
        return $this->hasMany(cashbook_transactions::class);
    }

    public function reconciliations()
    {
        return $this->hasMany(cashbook_reconcilation::class);
    }
}
