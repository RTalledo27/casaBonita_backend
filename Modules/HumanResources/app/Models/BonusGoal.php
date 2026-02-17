<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\HumanResources\Database\Factories\BonusGoalFactory;

class BonusGoal extends Model
{
    use HasFactory, SoftDeletes;


    protected $table = 'bonus_goals';
    protected $primaryKey = 'bonus_goal_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bonus_type_id',
        'goal_name',
        'min_achievement',
        'max_achievement',
        'bonus_amount',
        'bonus_percentage',
        'target_value',
        'employee_type',
        'team_id',
        'office_id',
        'is_active',
        'valid_from',
        'valid_until'
    ];


    protected $casts=[
        'min_achievement' => 'decimal:2',
        'max_achievement' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'bonus_percentage' => 'decimal:2',
        'target_value' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date'
    ];

    //RELACIONES
    public function bonusType()
    {
        return $this->belongsTo(BonusType::class, 'bonus_type_id', 'bonus_type_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function bonuses()
    {
        return $this->hasMany(Bonus::class, 'bonus_goal_id', 'bonus_goal_id');
    }

    //SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where('valid_from', '<=', $date)
            ->where(function($q) use ($date){
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $date);
            });
    }

    public function scopeForEmployeeType($query, $employeeType)
    {
        return $query->where(function ($q) use ($employeeType) {
            $q->whereNull('employee_type')
                ->orWhere('employee_type', $employeeType);
        });
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where(function ($q) use ($teamId) {
            $q->whereNull('team_id')
                ->orWhere('team_id', $teamId);
        });
    }

    public function scopeForOffice($query, $officeId)
    {
        return $query->where(function ($q) use ($officeId) {
            $q->whereNull('office_id')
                ->orWhere('office_id', $officeId);
        });
    }

    // Métodos de negocio
    public function isValidForDate($date = null): bool
    {
        $date = $date ?? now();
        if ($this->valid_from && $date < $this->valid_from) {
            return false;
        }
        if ($this->valid_until && $date > $this->valid_until) {
            return false;
        }
        return $this->is_active;
    }

    public function calculateBonusAmount($achievement, $baseSalary = null): float
    {
        if ($achievement < $this->min_achievement) {
            return 0;
        }
        if ($this->max_achievement && $achievement > $this->max_achievement) {
            $achievement = $this->max_achievement;
        }
        if ($this->bonus_amount) {
            return $this->bonus_amount;
        }
        if ($this->bonus_percentage && $baseSalary) {
            return ($baseSalary * $this->bonus_percentage) / 100;
        }
        return 0;
    }

    public function isApplicableToEmployee(Employee $employee): bool
    {
        if (!$this->is_active || !$this->isValidForDate()) {
            return false;
        }
        // Verificar tipo de empleado
        if ($this->employee_type && $employee->employee_type !== $this->employee_type) {
            return false;
        }
        // Verificar equipo
        if ($this->team_id && $employee->team_id !== $this->team_id) {
            return false;
        }
        // Verificar oficina
        if ($this->office_id && $employee->office_id !== $this->office_id) {
            return false;
        }
        return true;
    }
}
