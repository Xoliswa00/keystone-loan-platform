<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The Company model is a singleton — one record per deployment.
 * All NCA agreements, emails, and PDFs pull from this.
 */
class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'name', 'registration_no', 'ncr_number', 'vat_number', 'ncr_credit_category',
        'address', 'physical_address', 'postal_address', 'city', 'province', 'postal_code', 'country',
        'logo_path', 'tagline', 'phone', 'whatsapp_number', 'email', 'support_email', 'website',
        'authorised_signatory', 'signatory_title',
        'notification_from_email', 'notification_from_name', 'notification_cc',
        'tax_id',
    ];

    /** Singleton accessor — cached for 10 minutes */
    public static function settings(): ?self
    {
        return Cache::remember('company_settings', 600, fn () => static::first());
    }

    /** Bust cache when settings are saved */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('company_settings'));
    }

    public function getNotificationCcArray(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->notification_cc ?? ''))
        );
    }

    public function getFormattedAddress(): string
    {
        return implode(', ', array_filter([
            $this->physical_address,
            $this->city,
            $this->province,
            $this->postal_code,
        ]));
    }

    public function isComplete(): bool
    {
        return ! empty($this->ncr_number)
            && ! empty($this->registration_no)
            && ! empty($this->physical_address);
    }
}
