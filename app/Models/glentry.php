<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class glentry extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    public function glBatch()
    {
        return $this->belongsTo(glbatch::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(chart_of_accounts::class, 'gl_account_id');

    }
}
