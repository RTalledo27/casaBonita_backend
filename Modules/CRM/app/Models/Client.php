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

    // use Illuminate\Database\Eloquent\Builder;

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'like', "%{$filters['search']}%")
                    ->orWhere('last_name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('doc_number', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['sort_by']) && !empty($filters['sort_dir'])) {
            $query->orderBy($filters['sort_by'], $filters['sort_dir']);
        } else {
            $query->latest(); // default sort
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }





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


    public function familyMembers()
    {
        return $this->hasMany(FamilyMember::class, 'client_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }
    

}
