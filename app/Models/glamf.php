<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class glamf extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code', 'account_name', 'chart_of_account_id', 'account_level',
        'account_type', 'allow_manual_entry', 'is_postable', 'is_active', 'custom1', 'custom2', 'custom3',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function chart()
    {
        return $this->belongsTo(chart_of_accounts::class, 'chart_of_account_id');
    }

    public function branchAccounts()
    {
        return $this->hasMany(branches::class, 'gl_account_master_id');
    }
}
