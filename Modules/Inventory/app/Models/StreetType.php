<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Inventory\Database\Factories\StreetTypeFactory;

class StreetType extends Model
{
    use HasFactory;
    protected $primaryKey = 'street_type_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
    ];

    // protected static function newFactory(): StreetTypeFactory
    // {
    //     // return StreetTypeFactory::new();
    // }

    public function lots(){
        return $this->hasMany(Lot::class, 'street_type_id');
    }

}
