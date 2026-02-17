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
        'monthly_goal',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monthly_goal' => 'decimal:2',
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

    public function teams()
    {
        return $this->hasMany(Team::class, 'office_id', 'office_id');
    }

    /**
     * Calculate monthly sales achievement for this office (across all its teams)
     */
    public function calculateMonthlyAchievement($month, $year): float
    {
        if ($this->monthly_goal <= 0) {
            return 0;
        }

        $totalSales = $this->calculateMonthlySalesAmount($month, $year);
        return ($totalSales / $this->monthly_goal) * 100;
    }

    /**
     * Calculate total sales amount for this office in a period
     */
    public function calculateMonthlySalesAmount($month, $year): float
    {
        $total = 0;
        $employees = $this->employees()->active()->get();
        foreach ($employees as $employee) {
            $total += $employee->calculateMonthlySales($month, $year)->sum('total_price');
        }
        return $total;
    }

    /**
     * Calculate total sales count for this office in a period
     */
    public function calculateMonthlySalesCount($month, $year): int
    {
        $total = 0;
        $employees = $this->employees()->active()->get();
        foreach ($employees as $employee) {
            $total += $employee->calculateMonthlySalesCount($month, $year);
        }
        return $total;
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
