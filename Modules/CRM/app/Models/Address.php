<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Database\Factories\AddressFactory;

// use Modules\CRM\Database\Factories\AddressFactory;

class Address extends Model
{
    use HasFactory;

    protected $primaryKey = 'address_id';
    public    $timestamps  = false;

    //SOLUCION ERROR DE FACTORY
    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'client_id',
        'line1',
        'line2',
        'city',
        'state',
        'country',
        'zip_code'
    ];

    // protected static function newFactory(): AddressFactory
    // {
    //     // return AddressFactory::new();
    // }
    //RELACIONES
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    
}
