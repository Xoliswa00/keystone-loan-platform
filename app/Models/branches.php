<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class branches extends Model
{
    use HasFactory;

    protected $fillable = ['branch_code', 'branch_name', 'location', 'branch_type', 'company_id', 'is_active'];
}
