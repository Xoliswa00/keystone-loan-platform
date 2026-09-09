<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of reporting_account_snapshots — one loan, as at one reporting month.
 * Written only by AccountSnapshotService; treated as immutable once an
 * export_run has consumed its (period, generation).
 */
class AccountSnapshot extends Model
{
    use HasFactory;

    protected $table = 'reporting_account_snapshots';

    // Status buckets a bureau/partner submission understands.
    public const ACCOUNT_STATUSES = [
        'Open' => 'Open / performing',
        'Delinquent' => 'In arrears',
        'Closed' => 'Settled / closed',
        'WrittenOff' => 'Written off',
        'Legal' => 'Handed over / legal',
    ];

    public const STATUS_CLASSES = [
        'Open' => 'kc-badge-green',
        'Delinquent' => 'kc-badge-gold',
        'Closed' => 'kc-badge-silver',
        'WrittenOff' => 'kc-badge-red',
        'Legal' => 'kc-badge-red',
    ];

    protected $fillable = [
        'period', 'generation',
        'loan_id', 'loan_application_id', 'user_id', 'customer_id', 'account_no',
        'borrower_name', 'id_number', 'date_of_birth', 'gender', 'nationality', 'borrower_age',
        'address_raw', 'phone', 'email',
        'nca_credit_type', 'ncr_purpose_code', 'loan_type', 'product_code', 'term_months',
        'principal_amount', 'total_interest', 'total_fees', 'total_vat', 'total_amount_due',
        'instalment_amount', 'opening_balance', 'current_balance', 'arrears_amount',
        'last_payment_amount', 'write_off_amount', 'carried_forward_shortfall', 'principal_at_default',
        'disbursed_date', 'first_instalment_date', 'final_instalment_date', 'last_payment_date',
        'settled_date', 'written_off_date', 'first_default_date',
        'days_past_due', 'instalments_in_arrears', 'months_in_arrears', 'ifrs9_stage',
        'payment_status_code', 'payment_profile_24m',
        'account_status', 'loan_status_raw', 'under_dispute', 'dispute_reference',
        'snapshot_at', 'source_hash',
    ];

    protected $casts = [
        'generation' => 'integer',
        'date_of_birth' => 'date',
        'borrower_age' => 'integer',
        'term_months' => 'integer',
        'principal_amount' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_amount_due' => 'decimal:2',
        'instalment_amount' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'arrears_amount' => 'decimal:2',
        'last_payment_amount' => 'decimal:2',
        'write_off_amount' => 'decimal:2',
        'carried_forward_shortfall' => 'decimal:2',
        'principal_at_default' => 'decimal:2',
        'disbursed_date' => 'date',
        'first_instalment_date' => 'date',
        'final_instalment_date' => 'date',
        'last_payment_date' => 'date',
        'settled_date' => 'date',
        'written_off_date' => 'date',
        'first_default_date' => 'date',
        'days_past_due' => 'integer',
        'instalments_in_arrears' => 'integer',
        'months_in_arrears' => 'integer',
        'ifrs9_stage' => 'integer',
        'under_dispute' => 'boolean',
        'snapshot_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /** Rows of the newest generation for a period. */
    public function scopeLatestGeneration($query, string $period)
    {
        $gen = static::where('period', $period)->max('generation') ?? 0;

        return $query->where('period', $period)->where('generation', $gen);
    }
}
