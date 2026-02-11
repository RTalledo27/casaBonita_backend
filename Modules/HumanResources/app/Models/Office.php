<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'office_id';

    protected $fillable = [
        'name',
        'name_normalized',
        'code',
        'address',
        'city',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['status'];

    /**
     * Boot method to auto-normalize name on save
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-normalize name for case-insensitive uniqueness
            $model->name_normalized = mb_strtolower(trim($model->name));
        });
    }

    // Accessor for status (for frontend compatibility)
    public function getStatusAttribute()
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    // Mutator for status (for frontend compatibility)
    public function setStatusAttribute($value)
    {
        $this->attributes['is_active'] = $value === 'active';
    }

    /**
     * Find or create an office by name (case-insensitive)
     */
    public static function findOrCreateByName(string $name): self
    {
        $normalized = mb_strtolower(trim($name));
        
        return static::firstOrCreate(
            ['name_normalized' => $normalized],
            [
                'name' => trim($name),
                'is_active' => true,
            ]
        );
    }

    // --- RELATIONSHIPS ---
    public function employees()
    {
        return $this->hasMany(Employee::class, 'office_id', 'office_id');
    }

    // --- SCOPES ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActiveEmployeesCountAttribute()
    {
        return $this->employees()->where('employment_status', 'activo')->count();
    }
}
