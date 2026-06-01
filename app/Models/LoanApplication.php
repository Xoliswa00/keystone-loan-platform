<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use HasFactory;
    
    protected $guarded = [
    
    ];
    protected $table = 'loan_applications'; 
    public function loan()
{
    return $this->hasOne(Loan::class);
}



public function repaymentSchedules()
{
    return $this->hasMany(RepaymentSchedule::class, 'loan_id', 'id');
}


    public function user()
    {
        return $this->belongsTo(User::class);
    }

  public function loanfee()
{
    return $this->hasOne(LoanFee::class, 'loan_application_id');
}

    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'user_id');
    }
    public function loanDisbursements()
    {
        return $this->hasMany(LoanDisbursement::class, 'loan_application_id');
    }

}
