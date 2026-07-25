<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepaymentSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'repayment_schedules';

    protected $fillable = [
        'loan_id',           // references loan_applications.id
        'user_id',
        'installment_number',
        'emi_amount',
        'principal_amount',
        'interest_amount',
        'fee_amount',
        'due_date',
        'status',
        'gl_posted',
        'paid_at',
    ];

    protected $casts = [
        'emi_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'due_date' => 'date',
        'gl_posted' => 'boolean',
        'paid_at' => 'datetime',
    ];

    // ── Relationships ──

    // loan_id points to loan_applications.id — keep method name accurate
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanRepayments()
    {
        return $this->hasMany(LoanRepayment::class, 'repayment_schedule_id');
    }

    // Most recent manual-payment claim against this schedule (pending_review
    // / paid / rejected) — lets the client-facing view show a submitted
    // claim's status instead of the upload form once one exists.
    public function latestManualClaim()
    {
        return $this->hasOne(LoanRepayment::class, 'repayment_schedule_id')->latestOfMany();
    }

    // The posted LoanRepayment that actually paid this schedule — staff
    // navigate here to find the "Reverse Payment" action (loan_repayments.
    // show.blade.php), which otherwise has no list to be reached from.
    public function paidRepayment()
    {
        return $this->hasOne(LoanRepayment::class, 'repayment_schedule_id')
            ->where('status', 'paid')->latestOfMany();
    }

    public function customer()
    {
        return $this->hasOneThrough(
            Customer::class,
            LoanApplication::class,
            'id',      // FK on loan_applications
            'user_id', // FK on customers
            'loan_id', // local key on repayment_schedules
            'user_id'  // local key on loan_applications
        );
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeDpd($query, int $from, ?int $to = null)
    {
        $q = $query->where('status', 'pending')
            ->whereRaw('DATEDIFF(CURDATE(), due_date) >= ?', [$from]);

        if ($to !== null) {
            $q->whereRaw('DATEDIFF(CURDATE(), due_date) <= ?', [$to]);
        }

        return $q;
    }

    // ── Helpers ──

    public function daysOverdue(): int
    {
        if ($this->status !== 'pending') {
            return 0;
        }

        return max(0, now()->diffInDays($this->due_date, false) * -1);
    }

    public function ifrs9Stage(): int
    {
        $dpd = $this->daysOverdue();
        $settings = LendingSetting::current();

        return match (true) {
            $dpd >= $settings->ifrs9_stage3_dpd => 3,
            $dpd >= $settings->ifrs9_stage2_dpd => 2,
            default => 1,
        };
    }
}
