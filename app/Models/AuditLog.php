<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null; // immutable — no updated_at

    protected $fillable = [
        'event',
        'auditable_type',
        'auditable_id',
        'user_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'note',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    // ── Static helpers ──

    public static function record(
        string $event,
        Model  $model,
        array  $oldValues = [],
        array  $newValues = [],
        string $note = null
    ): self {
        return static::create([
            'event'          => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'user_id'        => auth()->id(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'note'           => $note,
        ]);
    }
}
