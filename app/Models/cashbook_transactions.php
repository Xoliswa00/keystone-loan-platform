<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cashbook_transactions extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'cashbook_account_id',
        'type',
        'amount',
        'transaction_date',
        'reference',
        'description',
        'source_module',
        'source_id',
        'created_by',
        'status',
        'gl_account_id',
        'batch_id',
        'customer_id',
        'vendor_id',
        'loan_id',
        'payment_method',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    public function account()
    {
        return $this->belongsTo(cashbook_accounts::class, 'cashbook_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batch()
    {
        return $this->belongsTo(cashbook_batches::class, 'batch_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(gl_accounts::class, 'gl_account_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(apvendors::class, 'vendor_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
