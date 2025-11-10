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
        'total_price',  // Mantener precio base
        'currency',
        'status',
        // Campos de sincronización con API externa (LOGICWARE)
        'external_id',
        'external_code',
        'external_sync_at',
        'external_data'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'external_sync_at' => 'datetime',
        'external_data' => 'array'
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
        return $this->belongsTo(StreetType::class, 'street_type_id', 'street_type_id');
    }
    
    public function media()
    {
        return $this->hasMany(LotMedia::class, 'lot_id','lot_id');
    }
    
    public function financialTemplate()
    {
        return $this->hasOne(LotFinancialTemplate::class, 'lot_id', 'lot_id');
    }
    
    public function reservations()
    {
        return $this->hasMany(\Modules\Sales\Models\Reservation::class, 'lot_id', 'lot_id');
    }
}
