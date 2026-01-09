<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\User;

class SalesCut extends Model
{
    use SoftDeletes;

    protected $table = 'sales_cuts';
    protected $primaryKey = 'cut_id';

    protected $fillable = [
        'cut_date',
        'cut_type',
        'status',
        'total_sales_count',
        'total_revenue',
        'total_down_payments',
        'total_payments_count',
        'total_payments_received',
        'paid_installments_count',
        'total_commissions',
        'cash_balance',
        'bank_balance',
        'notes',
        'summary_data',
        'closed_by',
        'closed_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'cut_date' => 'date',
        'total_sales_count' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_down_payments' => 'decimal:2',
        'total_payments_count' => 'integer',
        'total_payments_received' => 'decimal:2',
        'paid_installments_count' => 'integer',
        'total_commissions' => 'decimal:2',
        'cash_balance' => 'decimal:2',
        'bank_balance' => 'decimal:2',
        'summary_data' => 'array',
        'closed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Items del corte
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesCutItem::class, 'cut_id', 'cut_id');
    }

    /**
     * Usuario que cerró el corte
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'user_id');
    }

    /**
     * Usuario que revisó el corte
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    /**
     * Cerrar el corte
     */
    public function close($userId): void
    {
        $this->update([
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);
    }

    /**
     * Marcar como revisado
     */
    public function markAsReviewed($userId): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Verificar si está cerrado
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'reviewed', 'exported']);
    }

    /**
     * Scope para cortes abiertos
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope para cortes del día
     */
    public function scopeToday($query)
    {
        return $query->whereDate('cut_date', now()->toDateString());
    }

    /**
     * Scope para cortes del mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('cut_date', now()->year)
                    ->whereMonth('cut_date', now()->month);
    }
}
