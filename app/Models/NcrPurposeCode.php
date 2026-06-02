<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrPurposeCode extends Model
{
    protected $fillable = ['code', 'description', 'active'];
    protected $casts    = ['active' => 'boolean'];
}
