<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtRecoveryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'debt_recovery_id', 'loan_id', 'user_id',
        'amount', 'payment_date', 'payment_method',
        'payment_reference', 'gl_batch_reference',
        'received_by', 'notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function recovery()    { return $this->belongsTo(DebtRecovery::class, 'debt_recovery_id'); }
    public function loan()        { return $this->belongsTo(Loan::class); }
    public function receivedBy()  { return $this->belongsTo(User::class, 'received_by'); }
}
