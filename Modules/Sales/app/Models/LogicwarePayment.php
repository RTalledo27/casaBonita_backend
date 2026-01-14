<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogicwarePayment extends Model
{
    use HasFactory;

    protected $table = 'logicware_payments';

    protected $fillable = [
        'message_id',
        'correlation_id',
        'source_id',
        'contract_id',
        'schedule_id',
        'installment_number',
        'external_payment_number',
        'payment_date',
        'amount',
        'currency',
        'method',
        'bank_name',
        'reference_number',
        'status',
        'user_name',
        'raw',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'raw' => 'array',
    ];

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}

