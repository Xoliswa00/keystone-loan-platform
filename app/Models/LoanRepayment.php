<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'user_id',
        'repayment_schedule_id',
        'nupay_staging_id',
        'payment_amount',
        'principal_amount',
        'interest_amount',
        'fee_amount',
        'credit_applied',
        'credit_created',
        'nupay_fee',
        'payment_date',
        'due_date',
        'status',                   // paid | payment_failed | reversed
        'payment_method',           // nupay | bank_transfer | cash | card
        'payment_reference',
        'total_paid',
        'notes',
        'payment_received_by',
        'gl_batch_reference',
        'transaction_type',         // success | failed | canceled | reversed | manual
        'proof_of_payment_path',
        'submitted_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'reverses_repayment_id',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'credit_applied' => 'decimal:2',
        'credit_created' => 'decimal:2',
        'nupay_fee' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
        'verified_at' => 'datetime',
    ];

    // ── Relationships ──

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repaymentSchedule()
    {
        return $this->belongsTo(RepaymentSchedule::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'payment_received_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_repayment_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_repayment_id');
    }
}
