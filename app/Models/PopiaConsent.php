<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger — every grant/withdrawal is a new row, never an update,
 * so there's a full audit trail of consent history per POPIA. "Current"
 * status for a consent_type is always the most recent row for that type.
 */
class PopiaConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'consent_type', 'granted', 'ip_address', 'context', 'consented_at',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'consented_at' => 'datetime',
    ];

    public static function latestFor(int $userId, string $type): ?self
    {
        return static::where('user_id', $userId)
            ->where('consent_type', $type)
            ->orderByDesc('consented_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function isGranted(int $userId, string $type): bool
    {
        return (bool) static::latestFor($userId, $type)?->granted;
    }
}
