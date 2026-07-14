<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class arbatch_entries extends Model
{
    use HasFactory;

    protected $fillable = [
        'arbatch_id', 'gl_account_id', 'entry_type', 'amount', 'description', 'loan_id',
    ];

    public function batch()
    {
        return $this->belongsTo(arbatch::class, 'arbatch_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(gl_accounts::class, 'gl_account_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
