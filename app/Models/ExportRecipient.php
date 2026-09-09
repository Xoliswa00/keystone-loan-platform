<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportRecipient extends Model
{
    use HasFactory, SoftDeletes;

    public const KINDS = [
        'bureau' => 'Credit bureau',
        'partner' => 'Partner / funder',
        'internal' => 'Internal',
    ];

    // Phase 1 transports only. sftp / api land in Phase 2.
    public const TRANSPORTS = [
        'download' => 'Download from run history',
        'email' => 'Email to recipient',
    ];

    public const STATUS_CLASSES = [
        'bureau' => 'kc-badge-navy',
        'partner' => 'kc-badge-silver',
        'internal' => 'kc-badge-gold',
    ];

    protected $fillable = [
        'name', 'slug', 'kind',
        'contact_person', 'contact_email', 'contact_phone',
        'transport', 'email_recipients',
        'lawful_basis', 'active', 'notes',
        'sftp_host', 'sftp_port', 'sftp_username', 'sftp_remote_path',
        'api_endpoint', 'credential_env_key',
        'created_by',
    ];

    protected $casts = [
        'email_recipients' => 'array',
        'active' => 'boolean',
        'sftp_port' => 'integer',
    ];

    public function profiles()
    {
        return $this->hasMany(ExportProfile::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function isBureau(): bool
    {
        return $this->kind === 'bureau';
    }
}
