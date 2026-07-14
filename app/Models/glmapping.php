<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class glmapping extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'account_code', 'entry_type', 'description'];

    public function account()
    {
        return $this->belongsTo(gl_accounts::class, 'account_code', 'full_account_no');
    }
}
