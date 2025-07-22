<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\HumanResources\Database\Factories\BonusTypeFactory;

class BonusType extends Model
{
    use HasFactory, SoftDeletes;


    protected $table = 'bonus_types';
    protected $primaryKey = 'bonus_type_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type_code',
        'type_name',
        'description',
        'calculation_method',
        'is_automatic',
        'requires_approval',
        'applicable_employee_types',
        'frequency',
        'is_active'
    ];

    // protected static function newFactory(): BonusTypeFactory
    // {
    //     // return BonusTypeFactory::new();
    // }

    protected $casts = [
        'is_automatic' => 'boolean',
        'requires_approval' => 'boolean',
        'applicable_employee_types' => 'array',
        'is_active' => 'boolean'
    ];


    //RELACIONES
    public function bonusGoals()
    {
        return $this->hasMany(BonusGoal::class, 'bonus_type_id', 'bonus_type_id');
    }

    public function bonuses()
    {
        return $this->hasMany(Bonus::class, 'bonus_type_id', 'bonus_type_id');
    }

    //SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    public function scopeForEmployeeType($query, $employeeType)
    {
        return $query->whereJsonContains('applicable_employee_types', $employeeType);
    }

    //METODOS DE NEGOCIO
    public function isApplicableEmployee(Employee $employee): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if(!$employee->is_bonus_eligible)
        {
            return false;
        }

        return in_array($employee->employee_type, $this->applicable_employee_types ?? []);
    }

    public function getCalculationMethodLabelAttribute(): string
    {
        return match ($this->calculation_method) {
            'percentage_of_goal' => 'Porcentaje de Meta',
            'fixed_amount' => 'Monto Fijo',
            'sales_count' => 'Cantidad de Ventas',
            'collection_amount' => 'Monto de Cobranza',
            'attendance_rate' => 'Tasa de Asistencia',
            'custom' => 'Personalizado',
            default => 'Desconocido'
        };
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'biweekly' => 'Quincenal',
            'annual' => 'Anual',
            'one_time' => 'Una vez',
            default => 'Desconocido'
        };
    }
}
