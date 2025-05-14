<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Inventory\Database\Factories\LotFactory;

class Lot extends Model
{
    use HasFactory;

    protected $primaryKey = 'lot_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'manzana_id',
        'street_type_id',
        'num_lot',
        'area_m2',
        'area_construction_m2',
        'total_price',
        'funding',
        'BPP',
        'BFH',
        'initial_quota',
        'currency',
        'status'
    ];

    // protected static function newFactory(): LotFactory
    // {
    //     // return LotFactory::new();
    // }

    //RELACIONES
    public function manzana()
    {
        return $this->belongsTo(Manzana::class, 'manzana_id');
    }
    public function streetType()
    {
        return $this->belongsTo(StreetType::class);
    }
    public function media()
    {
        return $this->hasMany(LotMedia::class);
    }
}
