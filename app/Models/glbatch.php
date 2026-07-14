<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class glbatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'source_type', 'source_id', 'status', 'created_by', 'posted_at',
    ];

    public function entries()
    {
        return $this->hasMany(glentry::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function glAccount()
    {
        return $this->belongsTo(gl_accounts::class, 'gl_account_id');
    }
}
