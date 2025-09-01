<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Database\Factories\CrmInteractionFactory;
use Modules\Security\Models\User;

// use Modules\CRM\Database\Factories\CrmInteractionFactory;

class CrmInteraction extends Model
{
    use HasFactory;


    protected $primaryKey = 'interaction_id';
    public    $timestamps  = true;


    //SOLUCION ERROR DE FACTORY
    protected static function newFactory(): CrmInteractionFactory
    {
        return CrmInteractionFactory::new();
    }
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'client_id',
        'user_id',
        'date',
        'channel',
        'notes'
    ];

    // protected static function newFactory(): CrmInteractionFactory
    // {
    //     // return CrmInteractionFactory::new();
    // }

    //RELACIONES
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


}
