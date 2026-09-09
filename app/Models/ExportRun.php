<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportRun extends Model
{
    use HasFactory;

    public const STATUS_CLASSES = [
        'pending' => 'kc-badge-silver',
        'building' => 'kc-badge-silver',
        'built' => 'kc-badge-navy',
        'sending' => 'kc-badge-navy',
        'success' => 'kc-badge-green',
        'failed' => 'kc-badge-red',
        'skipped' => 'kc-badge-gold',
    ];

    protected $fillable = [
        'export_profile_id', 'period', 'snapshot_generation',
        'status', 'row_count',
        'file_disk', 'file_path', 'file_size', 'file_sha256',
        'transport', 'transport_result', 'profile_snapshot',
        'error_message', 'error_context',
        'started_at', 'finished_at', 'triggered_by',
    ];

    protected $casts = [
        'snapshot_generation' => 'integer',
        'row_count' => 'integer',
        'file_size' => 'integer',
        'transport_result' => 'array',
        'profile_snapshot' => 'array',
        'error_context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(ExportProfile::class, 'export_profile_id');
    }

    public function scopeSucceeded($query)
    {
        return $query->where('status', 'success');
    }

    public function hasFile(): bool
    {
        return $this->file_path
            && $this->file_disk
            && \Illuminate\Support\Facades\Storage::disk($this->file_disk)->exists($this->file_path);
    }
}
