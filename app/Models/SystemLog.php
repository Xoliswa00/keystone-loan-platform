<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    const UPDATED_AT = null;

    const CREATED_AT = null;

    protected $fillable = [
        'level',
        'channel',
        'message',
        'logged_at',
        'forwarded_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'forwarded_at' => 'datetime',
    ];

    public function scopeLevel($query, ?string $level)
    {
        return $level ? $query->where('level', strtolower($level)) : $query;
    }

    /**
     * Rows the Xquisite forwarder should ship: error-and-above, not yet sent.
     * Levels are stored lowercase (see App\Logging\DatabaseLogHandler).
     */
    public function scopeForwardable($query)
    {
        return $query
            ->whereNull('forwarded_at')
            ->whereIn('level', ['error', 'critical', 'alert', 'emergency']);
    }
}
