<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanStatusHistory extends Model
{
    protected $fillable = [
        'loan_id',
        'loan_application_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'ip_address',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
