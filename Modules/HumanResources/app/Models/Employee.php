<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sales\Models\Contract;
use Modules\Security\Models\User;

// use Modules\HumanResources\Database\Factories\EmployeeFactory;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'user_id',
        'employee_code',
        'employee_type',
        'base_salary',
        'variable_salary',
        'commission_percentage',
        'individual_goal',
        'is_commission_eligible',
        'is_bonus_eligible',
        'bank_account',
        'bank_name',
        'bank_cci',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'team_id',
        'supervisor_id',
        'hire_date',
        'termination_date',
        'employment_status',
        'contract_type',
        'work_schedule',
        'social_security_number',
        'afp_code',
        'cuspp',
        'health_insurance',
        'notes'
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'variable_salary' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'individual_goal' => 'decimal:2',
        'is_commission_eligible' => 'boolean',
        'is_bonus_eligible' => 'boolean',
        'hire_date' => 'date',
        'termination_date' => 'date'
    ];

    // --- RELACIONES ---
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id', 'employee_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'supervisor_id', 'employee_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'employee_id', 'employee_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'employee_id', 'employee_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'employee_id', 'employee_id');
    }

    public function bonuses()
    {
        return $this->hasMany(Bonus::class, 'employee_id', 'employee_id');
    }

    public function incentives()
    {
        return $this->hasMany(Incentive::class, 'employee_id', 'employee_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'advisor_id', 'employee_id');
    }

    // --- ATRIBUTOS ACCESORES ---
    public function getFullNameAttribute()
    {
        return $this->user ? $this->user->first_name . ' ' . $this->user->last_name : '';
    }

    public function getIsAdvisorAttribute()
    {
        return in_array($this->employee_type, ['asesor_inmobiliario', 'vendedor']);
    }

    /**
     * Verifica si el empleado es un asesor de ventas (elegible para ranking)
     */
    public function isSalesAdvisor(): bool
    {
        return in_array($this->employee_type, ['asesor_inmobiliario', 'vendedor']);
    }

    // --- SCOPES ---
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'activo');
    }

    public function scopeAdvisors($query)
    {
        return $query->whereIn('employee_type', ['asesor_inmobiliario', 'vendedor']);
    }

    // --- MÉTODOS DE CÁLCULO ---
    public function calculateMonthlySales($month, $year)
    {
        return $this->contracts()
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('status', 'vigente')
            ->get();
    }

    /**
     * Calcular CANTIDAD de contratos/ventas del mes
     */
    public function calculateMonthlySalesCount($month, $year)
    {
        return $this->contracts()
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('status', 'vigente')
            ->count();
    }

    /**
     * Calcular efectividad basada en CANTIDAD de ventas vs meta
     */
    public function calculateSalesCountAchievement($month, $year, $targetCount = 10)
    {
        $salesCount = $this->calculateMonthlySalesCount($month, $year);
        return $targetCount > 0 ? ($salesCount / $targetCount) * 100 : 0;
    }

    /**
     * Calcular CANTIDAD de contratos/ventas quincenales
     * $fortnight: 1 = primera quincena (1-15), 2 = segunda quincena (16-fin)
     */
    public function calculateFortnightlySalesCount($month, $year, $fortnight = 1)
    {
        $query = $this->contracts()
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('status', 'vigente');

        if ($fortnight == 1) {
            // Primera quincena: días 1-15
            $query->whereDay('sign_date', '<=', 15);
        } else {
            // Segunda quincena: días 16-fin del mes
            $query->whereDay('sign_date', '>', 15);
        }

        return $query->count();
    }

    /**
     * Calcular efectividad quincenal basada en CANTIDAD de ventas vs meta
     */
    public function calculateFortnightlyAchievement($month, $year, $fortnight, $targetCount = 6)
    {
        $salesCount = $this->calculateFortnightlySalesCount($month, $year, $fortnight);
        return $targetCount > 0 ? ($salesCount / $targetCount) * 100 : 0;
    }

    public function calculateGoalAchievement($month, $year)
    {
        $salesAmount = $this->calculateMonthlySales($month, $year)->sum('total_price');
        return $this->individual_goal > 0 ? ($salesAmount / $this->individual_goal) * 100 : 0;
    }

    public function calculateMonthlyCommissions($month, $year)
    {
        return $this->commissions()
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->sum('commission_amount');
    }

    public function calculateMonthlyBonuses($month, $year)
    {
        return $this->bonuses()
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->sum('bonus_amount');
    }

    public function getTotalMonthlyEarnings($month, $year)
    {
        $commissions = $this->calculateMonthlyCommissions($month, $year);
        $bonuses = $this->calculateMonthlyBonuses($month, $year);
        return $this->base_salary + $commissions + $bonuses;
    }

    public function currentMonthCommissions()
    {
        return $this->hasMany(Commission::class, 'employee_id', 'employee_id')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function currentMonthBonuses()
    {
        return $this->hasMany(Bonus::class, 'employee_id', 'employee_id')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}