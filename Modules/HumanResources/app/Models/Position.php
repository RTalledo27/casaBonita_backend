<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'position_id';

    protected $fillable = [
        'name',
        'name_normalized',
        'category',
        'is_commission_eligible',
        'is_bonus_eligible',
        'is_active',
    ];

    protected $casts = [
        'is_commission_eligible' => 'boolean',
        'is_bonus_eligible' => 'boolean',
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
            $model->name_normalized = mb_strtolower(trim($model->name));
        });
    }

    // Accessor for status (frontend compatibility)
    public function getStatusAttribute()
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    // Mutator for status (frontend compatibility)
    public function setStatusAttribute($value)
    {
        $this->attributes['is_active'] = $value === 'active';
    }

    /**
     * Find or create a position by name (case-insensitive)
     */
    public static function findOrCreateByName(string $name, string $category = 'admin'): self
    {
        $normalized = mb_strtolower(trim($name));

        return static::firstOrCreate(
            ['name_normalized' => $normalized],
            [
                'name' => trim($name),
                'category' => $category,
                'is_commission_eligible' => in_array($category, ['ventas']),
                'is_bonus_eligible' => in_array($category, ['ventas']),
                'is_active' => true,
            ]
        );
    }

    /**
     * Determinar categoría automáticamente basado en nombre del cargo
     */
    public static function guessCategoryFromName(string $name): string
    {
        $lower = mb_strtolower(trim($name));

        if (str_contains($lower, 'asesor') || str_contains($lower, 'ventas') || str_contains($lower, 'vendedor')) {
            return 'ventas';
        }
        if (str_contains($lower, 'gerente general') || str_contains($lower, 'director')) {
            return 'gerencia';
        }
        if (str_contains($lower, 'ingeniero') || str_contains($lower, 'sistemas') || str_contains($lower, 'tecnolog')) {
            return 'tech';
        }
        if (str_contains($lower, 'arquitecto') || str_contains($lower, 'arquitecta')) {
            return 'operaciones';
        }

        return 'admin';
    }

    /**
     * Find or create with auto-category detection
     */
    public static function findOrCreateSmart(string $name): self
    {
        $normalized = mb_strtolower(trim($name));

        $existing = static::where('name_normalized', $normalized)->first();
        if ($existing) {
            return $existing;
        }

        $category = static::guessCategoryFromName($name);

        return static::create([
            'name' => trim($name),
            'category' => $category,
            'is_commission_eligible' => $category === 'ventas',
            'is_bonus_eligible' => $category === 'ventas',
            'is_active' => true,
        ]);
    }

    // --- RELATIONSHIPS ---
    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id', 'position_id');
    }

    // --- SCOPES ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSales($query)
    {
        return $query->where('category', 'ventas');
    }

    public function scopeCommissionEligible($query)
    {
        return $query->where('is_commission_eligible', true);
    }

    public function getActiveEmployeesCountAttribute()
    {
        return $this->employees()->where('employment_status', 'activo')->count();
    }
}
