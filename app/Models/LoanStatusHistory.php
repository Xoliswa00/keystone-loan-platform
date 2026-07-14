<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanStatusHistory extends Model
{
    // Eloquent's default naming convention guesses 'loan_status_histories'
    // (irregular plural of 'history'), but the migration created the table
    // as 'loan_status_history' (singular) — override to match.
    protected $table = 'loan_status_history';

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
