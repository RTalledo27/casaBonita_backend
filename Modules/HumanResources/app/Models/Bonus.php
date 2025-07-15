<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\HumanResources\Database\Factories\BonusFactory;

class Bonus extends Model
{
    use HasFactory;

    protected $primaryKey = 'bonus_id';

    protected $fillable = [
        'employee_id',
        'bonus_type',
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

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

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

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pendiente');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'pagado');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('bonus_type', $type);
    }

    public function scopeByPeriod($query, $month, $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }
}
