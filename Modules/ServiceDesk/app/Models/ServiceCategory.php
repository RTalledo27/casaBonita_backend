<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all active categories
     */
    public static function getActive()
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Relationship with tickets
     */
    public function tickets()
    {
        return $this->hasMany(ServiceRequest::class, 'category_id');
    }
}
