<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Modules\HumanResources\Models\Employee;

// use Modules\Sales\Database\Factories\ReservationFactory;

class Reservation extends Model
{
    use HasFactory;

    protected $primaryKey = 'reservation_id';
    // protected $table = 'reservation';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'lot_id',
        'client_id',
        'advisor_id',
        'reservation_date',
        'expiration_date',
        'deposit_amount',
        'deposit_method',
        'deposit_reference',
        'deposit_paid_at',
        'status'
    ];

    protected $casts = [
        'deposit_paid_at' => 'datetime',
    ];


    // protected static function newFactory(): ReservationFactory
    // {
    //     // return ReservationFactory::new();
    // }

    //RELACIONES
    public function lot()
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function advisor()
    {
        return $this->belongsTo(Employee::class, 'advisor_id', 'employee_id');
    }
    public function contract()
    {
        return $this->hasOne(Contract::class, 'reservation_id');
    }
}
