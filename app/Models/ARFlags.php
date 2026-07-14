<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ARFlags extends Model
{
    use HasFactory;

    protected $fillable = ['flag_type', 'description', 'status', 'created_by'];

    public function flaggable()
    {
        return $this->morphTo();
    }
}
