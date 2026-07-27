<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * Characters usable in a generated customer_code. Excludes 0/O and 1/I —
     * this code doubles as the bank-facing payment_reference on disbursements
     * (see LoanController::approve()), so it has to survive a customer or
     * support agent reading/typing it back without ambiguity. Deliberately
     * random rather than sequential: a sequential code lets anyone guess
     * adjacent customers' codes, which matters since this is also used as a
     * payment reference on bank statements.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Generate a unique customer_code, e.g. CLF-8X9K2P.
     */
    public static function generateCode(string $prefix): string
    {
        do {
            $suffix = collect(range(1, 6))
                ->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])
                ->implode('');
            $code = "{$prefix}-{$suffix}";
        } while (self::where('customer_code', $code)->exists());

        return $code;
    }

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
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class, 'user_id', 'user_id');
    }

    // LoanApplication.php

    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class, 'loan_id', 'user_id');
    }

    public function paymentAdjustments()
    {
        return $this->hasMany(PaymentAdjustment::class);
    }
}
