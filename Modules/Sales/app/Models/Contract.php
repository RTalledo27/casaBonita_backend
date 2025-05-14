<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Accounting\Models\Invoice;

// use Modules\Sales\Database\Factories\ContractFactory;

class Contract extends Model
{
    use HasFactory;


    protected $primaryKey = 'contract_id';
    public    $timestamps  = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reservation_id',
        'contract_number',
        'sign_date',
        'total_price',
        'currency',
        'status'
    ];

    // protected static function newFactory(): ContractFactory
    // {
    //     // return ContractFactory::new();
    // }

    //RELACIONES
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
    public function schedules()
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
