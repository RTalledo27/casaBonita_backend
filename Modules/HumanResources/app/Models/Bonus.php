<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\HumanResources\Database\Factories\BonusFactory;

class Bonus extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'bonus_id';

    protected $fillable = [
        'employee_id',
        'bonus_type_id',
        'bonus_goal_id',
        'bonus_name',
        'bonus_amount',
        'target_amount',
        'achieved_amount',
        'achievement_percentage',
        'payment_status',
        'payment_date',
        'period_month',
        'period_year',
        'period_quarter',
        'created_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    protected $casts = [
        'bonus_amount' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'achievement_percentage' => 'decimal:2',
        'payment_date' => 'date',
        'approved_at' => 'datetime'
    ];

    //RELACIONES
    public function employee()
    {
        return $this->belongsTo(Employee::class,'employee_id','employee_id');
    }

    public function bonusType()
    {
        return $this->belongsTo(BonusType::class,'bonus_type_id','bonus_type_id');
    }

    public function bonusGoal()
    {
        return $this->belongsTo(BonusGoal::class,'bonus_goal_id','bonus_goal_id');
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class,'created_by','employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class,'approved_by','employee_id');
    }

    //SCOPES
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pendiente');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'pagado');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

       public function scopePendingApproval($query)
    {
        return $query->whereNull('approved_at')
                    ->whereHas('bonusType', function($q) {
                        $q->where('requires_approval', true);
                    });
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('bonus_type_id', $typeId);
    }

    public function scopeByPeriod($query, $month, $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeByQuarter($query, $quarter, $year)
    {
        return $query->where('period_quarter', $quarter)->where('period_year', $year);
    }

    // Métodos de negocio
    public function approve(Employee $approver): bool
    {
        if ($this->approved_at) {
            return false; // Already approved
        }

        $this->approved_by = $approver->employee_id;
        $this->approved_at = now();
        
        return $this->save();
    }

    public function markAsPaid(): bool
    {
        if ($this->payment_status === 'pagado') {
            return false; // Already paid
        }

        $this->payment_status = 'pagado';
        $this->payment_date = now()->toDateString();
        
        return $this->save();
    }

    public function cancel(): bool
    {
        if ($this->payment_status === 'pagado') {
            return false; // Cannot cancel if already paid
        }

        $this->payment_status = 'cancelado';
        return $this->save();
    }

    public function requiresApproval(): bool
    {
        return $this->bonusType && $this->bonusType->requires_approval && !$this->approved_at;
    }

    public function canBePaid(): bool
    {
        if ($this->payment_status === 'pagado') {
            return false;
        }

        if ($this->requiresApproval()) {
            return false;
        }

        return true;
    }

    // Atributos calculados
    public function getPaymentStatusLabelAttribute(): string
    {
        return match($this->payment_status) {
            'pendiente' => 'Pendiente',
            'pagado' => 'Pagado',
            'cancelado' => 'Cancelado',
            default => 'Desconocido'
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        if ($this->period_quarter) {
            return "Q{$this->period_quarter} {$this->period_year}";
        }

        if ($this->period_month && $this->period_year) {
            $months = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            ];
            
            return ($months[$this->period_month] ?? 'Mes desconocido') . ' ' . $this->period_year;
        }

        return 'Sin período definido';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->requiresApproval()) {
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        }

        return match($this->payment_status) {
            'pendiente' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            'pagado' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'cancelado' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
        };
    }

    // Static calculation methods (compatibility with existing code)
    public static function calculateIndividualGoalBonus($achievementPercentage)
    {
        if ($achievementPercentage >= 120) return 1000;
        if ($achievementPercentage >= 102) return 600;
        return 0;
    }

    public static function calculateTeamGoalBonus($achievementPercentage, $employeeType)
    {
        if ($employeeType !== 'vendedor') return 0;
        if ($achievementPercentage >= 110) return 500;
        if ($achievementPercentage >= 102) return 300;
        return 0;
    }

    public static function calculateQuarterlyBonus($salesCount, $employeeType)
    {
        return ($employeeType === 'asesor_inmobiliario' && $salesCount >= 30) ? 1000 : 0;
    }

    public static function calculateBiweeklyBonus($salesCount, $employeeType)
    {
        return ($employeeType === 'asesor_inmobiliario' && $salesCount >= 6) ? 500 : 0;
    }

    public static function calculateCollectionBonus($collectionAmount, $employeeType)
    {
        return ($employeeType === 'asesor_inmobiliario' && $collectionAmount >= 50000) ? 500 : 0;
    }
}
