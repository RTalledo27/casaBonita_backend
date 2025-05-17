<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Sales\Models\Reservation;

use Modules\CRM\Database\Factories\ClientFactory;

class Client extends Model
{
    use HasFactory;
    //protected $table = 'clients';
    protected $primaryKey = 'client_id';
    public $timestamps = true;

    //SOLUCION ERROR DE FACTORY
    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'doc_type',
        'doc_number',
        'email',
        'primary_phone',
        'secondary_phone',
        'marital_status',
        'type',
        'date',
        'occupation',
        'salary',
        'family_group'
    ];

    // protected static function newFactory(): ClientFactory
    // {
    //     // return ClientFactory::new();
    // }

    //RELACIONES 
    public function spouses(){
        return $this->belongsToMany(Client::class, 'spouses', 'client_id', 'partner_id')
            ->using(Spouse::class);
            //->withPivot('id', 'client_id', 'spouse_id')
            //->withTimestamps();
        ;
    }

    public function addresses(){
        return $this->hasMany(Address::class, 'client_id');
    }
    public function interactions()
    {
        return $this->hasMany(CrmInteraction::class, 'client_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }
    

}
