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
        'due_date',
        'amount',
        'status'

    ];

    // protected static function newFactory(): PaymentSheduleFactory
    // {
    //     // return PaymentSheduleFactory::new();
    // }

    //RELACIONES
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
