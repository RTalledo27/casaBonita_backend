<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Inventory\Database\Factories\ManzanaFactory;

class Manzana extends Model
{
    use HasFactory;

    protected $table = 'manzana';
    protected $primaryKey = 'manzana_id';   
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
    ];

    // protected static function newFactory(): ManzanaFactory
    // {
    //     // return ManzanaFactory::new();
    // }

    //RELACIONES
    public function lots()
    {
        return $this->hasMany(Lot::class, 'manzana_id');
    }
}
