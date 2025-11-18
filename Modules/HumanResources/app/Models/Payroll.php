<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\HumanResources\Database\Factories\PayrollFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $primaryKey = 'payroll_id';

    protected $fillable = [
        'employee_id',
        'payroll_period',
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        'base_salary',
        'family_allowance',
        'commissions_amount',
        'bonuses_amount',
        'overtime_amount',
        'other_income',
        'gross_salary',
        'pension_system',
        'afp_provider',
        'afp_contribution',
        'afp_commission',
        'afp_insurance',
        'onp_contribution',
        'total_pension',
        'employer_essalud',
        'employee_essalud',
        'rent_tax_5th',
        'other_deductions',
        'total_deductions',
        'net_salary',
        'currency',
        'status',
        'processed_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'pay_date' => 'date',
        'base_salary' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'commissions_amount' => 'decimal:2',
        'bonuses_amount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'other_income' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'afp_contribution' => 'decimal:2',
        'afp_commission' => 'decimal:2',
        'afp_insurance' => 'decimal:2',
        'onp_contribution' => 'decimal:2',
        'total_pension' => 'decimal:2',
        'employer_essalud' => 'decimal:2',
        'employee_essalud' => 'decimal:2',
        'rent_tax_5th' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function processor()
    {
        return $this->belongsTo(Employee::class, 'processed_by', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('payroll_period', $period);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'procesado');
    }

    // Alias para compatibilidad con el repositorio
    public function processedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'processed_by', 'employee_id');
    }
    public function approvedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'aprobado');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'pagado');
    }
}
