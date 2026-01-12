<?php

namespace Modules\Collections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

class PaymentSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_schedules';
    protected $primaryKey = 'schedule_id';
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_date',
        'paid_amount',
        'payment_method',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    // Estados posibles
    const STATUS_PENDING = 'pendiente';
    const STATUS_PAID = 'pagado';
    const STATUS_OVERDUE = 'vencido';
    const STATUS_CANCELLED = 'cancelado';

    // Relaciones
    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function payments()
    {
        return $this->hasMany(\Modules\Collections\Models\CustomerPayment::class, 'ar_id');
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        return $this->due_date < now() && $this->status !== self::STATUS_PAID;
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->is_overdue) {
            return 0;
        }
        return now()->diffInDays($this->due_date);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_PAID);
    }

    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    // Métodos de negocio
    public function markAsPaid($paymentDate = null, $amount = null, $method = null, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_date' => $paymentDate ?? now(),
            'paid_amount' => $amount ?? $this->amount,
            'payment_method' => $method,
            'notes' => $notes
        ]);
    }

    public function canBePaid()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE]);
    }

    public function updateStatus()
    {
        if ($this->status === self::STATUS_PAID) {
            return;
        }

        if ($this->due_date < now()) {
            $this->status = self::STATUS_OVERDUE;
        } else {
            $this->status = self::STATUS_PENDING;
        }

        $this->save();
    }
}
