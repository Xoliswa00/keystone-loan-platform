<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'file_hash',
        'original_name',
        'mime_type',
        'file_size_bytes',
        'document_date',
        'verified',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'content_check_status',
        'content_check_notes',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'document_date' => 'date',
    ];

    const TYPES = [
        'id_document' => 'SA ID Document',
        'payslip' => 'Latest Payslip',
        'bank_statement' => 'Bank Statement (3 months)',
        'proof_of_address' => 'Proof of Address',
        'other' => 'Other Document',
    ];

    // Documents must be within 3 months for payslips and bank statements
    const RECENCY_MONTHS = [
        'payslip' => 3,
        'bank_statement' => 3,
        'proof_of_address' => 3,
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── Helpers ──

    public function isRecent(): bool
    {
        $limit = self::RECENCY_MONTHS[$this->document_type] ?? null;

        if (! $limit || ! $this->document_date) {
            return true; // no recency rule for this type
        }

        return $this->document_date->gte(Carbon::now()->subMonths($limit)->startOfMonth());
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? 'Document';
    }

    // ── Scopes ──

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }
}
