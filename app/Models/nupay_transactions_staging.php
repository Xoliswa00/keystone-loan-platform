<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class nupay_transactions_staging extends Model
{
    use HasFactory;

    protected $table = 'nupay_transactions_stagings';

    protected $fillable = [
        'import_ref',
        'import_id',
        'mandate_id',
        'mandate_request_tran_id',
        'contract_reference',
        'debtor_id',           // SA ID number — matches users.ID_Number
        'debtor_name',
        'action_date',
        'action_date_time',
        'instalment_amount',
        'transaction_type',    // success | failed | canceled | reversed
        'transaction_status',
        'bank_reference',
        'posted_at',
    ];

    protected $casts = [
        'instalment_amount' => 'decimal:2',
        'action_date'       => 'date',
        'action_date_time'  => 'datetime',
        'posted_at'         => 'datetime',
    ];

    // ── Relationships ──

    public function importBatch()
    {
        return $this->belongsTo(import_batch::class, 'import_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'debtor_id', 'ID_Number');
    }

    // ── Scopes ──

    public function scopeUnposted($query)
    {
        return $query->whereNull('posted_at');
    }

    public function scopeByRef($query, string $ref)
    {
        return $query->where('import_ref', $ref);
    }
}
