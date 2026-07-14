<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class gl_accounts extends Model
{
    use HasFactory;

    protected $fillable = [
        'gl_code',
        'chart_of_account_id',

        'branch_id',
        'opening_balance',
        'current_balance',
    ];

    public function branch()
    {
        return $this->belongsTo(Branches::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(chart_of_accounts::class, 'chart_of_account_id');
    }

    public function cashbookAccounts()
    {
        return $this->hasMany(cashbook_accounts::class, 'gl_account_id');
    }

    public function mappings()
    {
        return $this->hasMany(glmapping::class, 'account_code', 'full_account_no');
    }
}
