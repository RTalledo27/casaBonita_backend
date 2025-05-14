<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Inventory\Database\Factories\LotMediaFactory;

class LotMedia extends Model
{
    use HasFactory;

    protected $primaryKey = 'media_id';
    // protected $table = 'lot_media';
    public $timestamps = false; 
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'lot_id',
        'url',
        'type',
        'uploaded_at'
    ];

    // protected static function newFactory(): LotMediaFactory
    // {
    //     // return LotMediaFactory::new();
    // }

    //RELACIONES
    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}
