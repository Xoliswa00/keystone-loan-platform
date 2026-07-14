<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class chart_of_accounts extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_category', 'account_group', 'account_type',
        'statement_section', 'note_reference', 'custom_fields', 'is_active', 'account_code',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'is_active' => 'boolean',
    ];

    public function masterAccounts()
    {
        return $this->hasMany(gl_accounts::class, 'chart_of_account_id');
    }

    public function mappings()
    {
        return $this->hasMany(glmapping::class, 'account_code', 'account_code');
    }

    public function getFullAccountNoAttribute()
    {
        return $this->account_code;
    }
}
