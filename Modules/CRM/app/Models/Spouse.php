<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\CRM\Database\Factories\SpouseFactory;

// use Modules\CRM\Database\Factories\SpouseFactory;

class Spouse extends Pivot
{
    use HasFactory;


    protected $primaryKey = 'spouse_id';
   

    //SOLUCION ERROR DE FACTORY
     protected static function newFactory(): SpouseFactory
     {
         return SpouseFactory::new();
     }
    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'spouses';
    public    $incrementing = true;
    public    $timestamps   = false;
    protected $fillable    = ['client_id', 'partner_id'];

    // protected static function newFactory(): SpouseFactory
    // {
    //     // return SpouseFactory::new();
    // }
}
