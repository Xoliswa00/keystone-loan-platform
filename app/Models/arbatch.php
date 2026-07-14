<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class arbatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'customer_id', 'source_type', 'source_id', 'total_amount', 'status', 'posted_to_gl', 'created_by', 'approved_by', 'approved_at',
    ];

    public function entries()
    {
        return $this->hasMany(arbatch_entries::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function source()
    {
        return $this->morphTo();
    }
}
