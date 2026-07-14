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
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function scopeLevel($query, ?string $level)
    {
        return $level ? $query->where('level', strtolower($level)) : $query;
    }
}
