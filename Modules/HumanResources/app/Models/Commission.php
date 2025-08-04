<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Sales\Models\Contract;

// use Modules\HumanResources\Database\Factories\CommissionFactory;

class Commission extends Model
{
    use HasFactory;

    protected $primaryKey = 'commission_id';

    protected $fillable = [
        'employee_id',
        'contract_id',
        'commission_type',
        'sale_amount',
        'installment_plan',
        'commission_percentage',
        'commission_amount',
        'payment_status',
        'payment_date',
        'period_month',
        'period_year',
        'commission_period',
        'payment_period',
        'payment_percentage',
        'status',
        'parent_commission_id',
        'payment_part',
        'notes',
        'payment_type',
        'total_commission_amount',
        'sales_count'
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total_commission_amount' => 'decimal:2',
        'payment_percentage' => 'decimal:2',
        'payment_date' => 'date',
        'sales_count' => 'integer',
        'payment_part' => 'integer'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public static function calculateCommissionPercentage($salesCount, $installmentPlan)
    {
        $commissionTable = [
            'short_term' => [10 => 4.20, 8 => 4.00, 6 => 3.00, 'default' => 2.00],
            'long_term' => [10 => 3.00, 8 => 2.50, 6 => 1.50, 'default' => 1.00]
        ];

        $planType = in_array($installmentPlan, [12, 24, 36]) ? 'short_term' : 'long_term';
        $table = $commissionTable[$planType];

        if ($salesCount >= 10) return $table[10];
        if ($salesCount >= 8) return $table[8];
        if ($salesCount >= 6) return $table[6];
        return $table['default'];
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pendiente');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'pagado');
    }

    public function scopeByPeriod($query, $month, $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeByCommissionPeriod($query, $period)
    {
        return $query->where('commission_period', $period);
    }

    public function scopeByPaymentPeriod($query, $period)
    {
        return $query->where('payment_period', $period);
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', 'partially_paid');
    }

    public function scopeFullyPaid($query)
    {
        return $query->where('status', 'fully_paid');
    }

    // Relación con comisión padre (para pagos divididos)
    public function parentCommission()
    {
        return $this->belongsTo(Commission::class, 'parent_commission_id', 'commission_id');
    }

    // Relación con comisiones hijas (partes del pago)
    public function childCommissions()
    {
        return $this->hasMany(Commission::class, 'parent_commission_id', 'commission_id');
    }

    /**
     * Verifica si esta comisión es un pago dividido
     */
    public function isSplitPayment(): bool
    {
        return !is_null($this->parent_commission_id);
    }

    /**
     * Verifica si esta comisión tiene pagos divididos
     */
    public function hasSplitPayments(): bool
    {
        return $this->childCommissions()->exists();
    }

    /**
     * Obtiene el monto total de todos los pagos relacionados
     */
    public function getTotalSplitAmount(): float
    {
        if ($this->isSplitPayment()) {
            return $this->parentCommission->childCommissions()->sum('commission_amount');
        }
        
        return $this->childCommissions()->sum('commission_amount') + $this->commission_amount;
    }

    /**
     * Genera el período de comisión basado en mes y año
     */
    public static function generateCommissionPeriod(int $month, int $year): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * Genera el período de pago con sufijo de parte
     */
    public static function generatePaymentPeriod(int $month, int $year, int $part = 1): string
    {
        return sprintf('%04d-%02d-P%d', $year, $month, $part);
    }
}
