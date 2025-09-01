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
        foreach ($this->members as $member) {
            $totalSales += $member->calculateMonthlySales($month, $year)->sum('total_price');
        }

        return $this->monthly_goal > 0 ? ($totalSales / $this->monthly_goal) * 100 : 0;
    }

    public function getActiveMembersAttribute()
    {
        return $this->members()->where('employment_status', 'activo')->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
