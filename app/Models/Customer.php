<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
     protected $fillable = [
        'user_id',
        'customer_code',
        'customer_type',
        'payment_terms',
        'status',
        'credit_limit',
        'current_balance',
        'notes',
    ];

    /**
     * Get the user that this customer profile belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }



     public function payments()
    {
        return $this->hasMany(arpayment::class);
    }

     public function loans()
    {
        return $this->hasMany(LoanApplication::class, 'user_id', 'user_id');
    }
  



     public function cashbook_transactions()
    {
        return $this->hasMany(cashbook_transactions::class, 'loan_id', 'user_id');
    }

     public function loansApproved()
    {
        return $this->hasMany(LoanApplication::class, 'reviewer_id', 'user_id');
    }


    /**
     * Scope for active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    // Customer.php
public function loanApplications() {
    return $this->hasMany(LoanApplication::class, 'user_id', 'user_id');
}

// LoanApplication.php



public function repaymentSchedules() {
    return $this->hasMany(RepaymentSchedule::class,'loan_id','user_id');
}



}
