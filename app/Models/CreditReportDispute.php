<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditReportDispute extends Model
{
    use HasFactory;

    public const STATUSES = ['open' => 'Open', 'resolved' => 'Resolved'];

    public const STATUS_CLASSES = ['open' => 'kc-badge-gold', 'resolved' => 'kc-badge-green'];

    protected $fillable = [
        'loan_id', 'raised_by', 'raised_at', 'respond_by',
        'reason', 'reference', 'status',
        'resolution_note', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'raised_at' => 'datetime',
        'respond_by' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->respond_by?->isPast();
    }
}
