<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\HumanResources\Database\Factories\TeamFactory;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'team_id';

    protected $fillable = [
        'team_name',
        'team_code',
        'description',
        'team_leader_id',
        'office_id',
        'is_active',
        'monthly_goal',
    ];

    protected $casts = [
        'monthly_goal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['status'];

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

    public function leader()
    {
        return $this->belongsTo(Employee::class, 'team_leader_id', 'employee_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'team_id', 'team_id');
    }

    public function members()
    {
        return $this->hasMany(Employee::class, 'team_id', 'team_id');
    }

    public function calculateMonthlyAchievement($month, $year)
    {
        $totalSales = 0;
        $totalCount = 0;
        foreach ($this->members()->active()->get() as $member) {
            $sales = $member->calculateMonthlySales($month, $year);
            $totalSales += $sales->sum('total_price');
            $totalCount += $sales->count();
        }

        return $this->monthly_goal > 0 ? ($totalSales / $this->monthly_goal) * 100 : 0;
    }

    /**
     * Calculate team monthly sales count
     */
    public function calculateMonthlySalesCount($month, $year): int
    {
        $total = 0;
        foreach ($this->members()->active()->get() as $member) {
            $total += $member->calculateMonthlySalesCount($month, $year);
        }
        return $total;
    }

    /**
     * Calculate team monthly sales amount
     */
    public function calculateMonthlySalesAmount($month, $year): float
    {
        $total = 0;
        foreach ($this->members()->active()->get() as $member) {
            $total += $member->calculateMonthlySales($month, $year)->sum('total_price');
        }
        return $total;
    }

    public function getActiveMembersAttribute()
    {
        return $this->members()->where('employment_status', 'activo')->count();
    }

    /**
     * Find or create a team by name (case-insensitive)
     */
    public static function findOrCreateByName(string $name): self
    {
        $normalized = mb_strtolower(trim($name));
        
        // Search case-insensitively
        $existing = static::whereRaw('LOWER(team_name) = ?', [$normalized])->first();
        
        if ($existing) {
            return $existing;
        }
        
        // Create new team
        return static::create([
            'team_name' => trim($name),
            'is_active' => true,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
