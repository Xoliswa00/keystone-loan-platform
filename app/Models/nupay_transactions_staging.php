<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class nupay_transactions_staging extends Model
{
    use HasFactory;

    protected $table = 'nupay_transactions_stagings';

    /**
     * Was previously missing ~35 of the ~48 real columns (and carried 3
     * fields — action_date_time, transaction_status, bank_reference — that
     * don't exist in the migration at all). Eloquent's mass-assignment
     * guard silently drops anything not listed here, so NupayImportService
     * was discarding almost every field it parsed on every import
     * regardless of how correctly it read the source file. Kept in the same
     * order as the migration (2026_01_16_032346_create_nupay_transactions_
     * stagings_table.php) so the two stay easy to diff against each other.
     */
    protected $fillable = [
        'import_id',
        'transaction_type',    // success | failed | canceled | reversed | tracking
        'approved',
        'mandate_id',
        'mandate_request_tran_id',
        'tracking',
        'mandate_reference_number',
        'contract_reference',
        'service_name',
        'debtor_bank',
        'client_reference',
        'creditor_bank',
        'date_of_first_instalment',
        'date_loaded',
        'action_date',
        'cycle_date',
        'date_created',
        'instalment_amount',
        'tpf_value',
        'original_amount',
        'total_amount',
        'insurance_amount',
        'authentication',
        'user_reference',
        'status',
        'instalment',
        'original_instalment',
        'rescheduled_instalment',
        'total_instalments',
        'debtor_id',           // SA ID number — matches users.ID_Number
        'debtor_name',
        'debtor_account_number',
        'debtor_account_type',
        'debtor_branch_number',
        'debtor_phone_number',
        'debtor_email',
        'response_date_time',
        'employer_code',
        'merchant_number',
        'frequency',
        'insurer',
        'insurance_id',
        'active',
        'response_description',
        'response_code',
        'disputable',
        'reason_code',
        'reason_code_description',
        'raw_row_json',
        'import_ref',
        'posted_at',
    ];

    protected $casts = [
        'instalment_amount' => 'decimal:2',
        'action_date' => 'date',
        'action_date_time' => 'datetime',
        'posted_at' => 'datetime',
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
