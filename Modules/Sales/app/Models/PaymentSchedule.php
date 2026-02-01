<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Sales\Database\Factories\PaymentSheduleFactory;

class PaymentSchedule extends Model
{
    use HasFactory;


    protected $primaryKey = 'schedule_id';
    public    $timestamps  = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'contract_id',
        'installment_number',
        'due_date',
        'amount',
        'amount_paid',
        'status',
        'payment_date',
        'payment_method',
        'notes',
        'type',
        'currency',
        'paid_date',
        'logicware_schedule_det_id',
        'logicware_paid_amount'
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'logicware_paid_amount' => 'decimal:2',
    ];

    // protected static function newFactory(): PaymentSheduleFactory
    // {
    //     // return PaymentSheduleFactory::new();
    // }

    //RELACIONES
    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'schedule_id', 'schedule_id');
    }
}
