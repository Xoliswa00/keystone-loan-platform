<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class glpost extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_id',
        'account_id',
        'debit',
        'credit',
        'post_date',
        'reference',
        'module',
    ];
}
