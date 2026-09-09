<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportProfile extends Model
{
    use HasFactory, SoftDeletes;

    public const FORMATS = [
        'csv' => 'CSV',
        'delimited' => 'Delimited (custom separator)',
    ];

    public const COLUMN_FORMATS = [
        'string' => 'Text',
        'integer' => 'Whole number',
        'money' => 'Money (2dp, no separators)',
        'upper' => 'Text (UPPERCASE)',
        'date:Y-m-d' => 'Date (YYYY-MM-DD)',
        'date:Ymd' => 'Date (YYYYMMDD)',
        'date:d/m/Y' => 'Date (DD/MM/YYYY)',
    ];

    protected $fillable = [
        'export_recipient_id', 'name', 'slug',
        'dataset', 'format', 'delimiter', 'enclosure', 'include_header', 'line_ending',
        'columns', 'filters', 'filename_pattern',
        'schedule_day_of_month', 'period_offset', 'active',
        'created_by',
    ];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'include_header' => 'boolean',
        'schedule_day_of_month' => 'integer',
        'period_offset' => 'integer',
        'active' => 'boolean',
    ];

    public function recipient()
    {
        return $this->belongsTo(ExportRecipient::class, 'export_recipient_id');
    }

    public function runs()
    {
        return $this->hasMany(ExportRun::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Reporting period this profile targets when run on $runDate. */
    public function periodFor(\DateTimeInterface $runDate): string
    {
        return \Illuminate\Support\Carbon::instance(\Illuminate\Support\Carbon::parse($runDate))
            ->startOfMonth()
            ->subMonthsNoOverflow(max(0, (int) $this->period_offset))
            ->format('Y-m');
    }
}
