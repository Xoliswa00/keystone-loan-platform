<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;
  
    protected $fillable = [
        'user_id',
        'loan_type',
        'loan_amount',
        'interest_rate',
        'loan_term',
        'collateral',
        'status',
        'approved_amount',
        'disbursed_date','loan_application_id',
        'remaining_balance',
        'approver_id',
        'processed_at',
        'approval_comments',
        'approved_at',
        'next_payment_date',
        'installment_frequency',    
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
    public function application()
{
    return $this->belongsTo(LoanApplication::class, 'loan_application_id');
}

    
  

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function interest()
    {
        return $this->hasOne(LoanInterest::class);
    }




    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class);
    }

    public function fees() {
    return $this->hasOne(LoanFee::class);
}
public function disbursements() {
    return $this->hasMany(LoanDisbursement::class, 'loan_id');  
}
public function customer()
{
    return $this->belongsTo(Customer::class, 'customer_id');
}



}
