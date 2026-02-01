<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Modules\HumanResources\Models\Employee;

class SalesCutItem extends Model
{
    protected $table = 'sales_cut_items';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'cut_id',
        'item_type',
        'contract_id',
        'payment_schedule_id',
        'payment_id',
        'employee_id',
        'amount',
        'commission',
        'payment_method',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Corte al que pertenece
     */
    public function cut(): BelongsTo
    {
        return $this->belongsTo(SalesCut::class, 'cut_id', 'cut_id');
    }

    /**
     * Contrato relacionado
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    /**
     * Cuota de pago relacionada
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id', 'schedule_id');
    }

    /**
     * Empleado/Asesor relacionado
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope para ventas
     */
    public function scopeSales($query)
    {
        return $query->where('item_type', 'sale');
    }

    /**
     * Scope para pagos
     */
    public function scopePayments($query)
    {
        return $query->where('item_type', 'payment');
    }

    /**
     * Scope para comisiones
     */
    public function scopeCommissions($query)
    {
        return $query->where('item_type', 'commission');
    }
}
