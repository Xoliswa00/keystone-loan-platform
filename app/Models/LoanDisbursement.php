<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'disbursed_amount', 'disbursement_method', 'disbursement_date', 'approver_id',
        'payment_reference', 'proof_of_payment', 'payment_realiser_id', 'status',
    ];

    // Relationship to the approver (user)
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // Relationship to the loan
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function cashbookTransaction()
    {
        return $this->belongsTo(cashbook_transactions::class);
    }
}
