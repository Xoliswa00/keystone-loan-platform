<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepaymentSchedule extends Model
{
    use HasFactory;

    protected $table = 'repayment_schedules'; 

    
    protected $fillable = [
        'loan_id',
        'emi_amount',
        'due_date',
        'status',
    ];

    public function loan()
    {
        return $this->belongsTo(LoanApplication::class);
    }
    public function customer()
    {
        return $this->hasOneThrough(
            Customer::class,
            LoanApplication::class,
            'id', // Foreign key on LoanApplication table...
            'user_id', // Foreign key on Customer table...
            'loan_id', // Local key on RepaymentSchedule table...
            'user_id'  // Local key on LoanApplication table...
        );
    }
    
}
