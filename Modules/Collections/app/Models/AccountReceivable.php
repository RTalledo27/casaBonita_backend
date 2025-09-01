<?php

namespace Modules\Collections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Models\Client;
use Modules\Sales\Models\Contract;
use Modules\Security\Models\User;


class AccountReceivable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_receivable';
    protected $primaryKey = 'ar_id';

    protected $fillable = [
        'client_id',
        'contract_id',
        'ar_number',
        'issue_date',
        'due_date',
        'original_amount',
        'outstanding_amount',
        'currency',
        'status',
        'assigned_collector_id',
        'notes'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'original_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    // Estados posibles
    const STATUS_PENDING = 'PENDING';
    const STATUS_PARTIAL = 'PARTIAL';
    const STATUS_PAID = 'PAID';
    const STATUS_OVERDUE = 'OVERDUE';
    const STATUS_CANCELLED = 'CANCELLED';

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'assigned_collector_id', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'ar_id');
    }

    // Accessors
    public function getAgingDaysAttribute()
    {
        return $this->due_date->isPast() ? $this->due_date->diffInDays(now()) : 0;
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date->isPast() && $this->outstanding_amount > 0;
    }

    public function getPaymentPercentageAttribute()
    {
        $percentage = 0;
        if ($this->original_amount != 0) {
            $percentage = round((($this->original_amount - $this->outstanding_amount) / $this->original_amount) * 100, 2);
        }
        return $percentage;
    }

    public function getAgingRangeAttribute()
    {
        $days = $this->aging_days;
        $range = 'over-90';

        if ($days <= 0) {
            $range = 'current';
        } elseif ($days <= 30) {
            $range = '1-30';
        } elseif ($days <= 60) {
            $range = '31-60';
        } elseif ($days <= 90) {
            $range = '61-90';
        }

        return $range;
    }

    // Scopes
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('outstanding_amount', '>', 0);
    }

    public function scopeByCollector($query, $collectorId)
    {
        return $query->where('assigned_collector_id', $collectorId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // Métodos de negocio
    public function updateStatus()
    {
        if ($this->outstanding_amount <= 0) {
            $this->status = self::STATUS_PAID;
        } elseif ($this->outstanding_amount < $this->original_amount) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($this->is_overdue) {
            $this->status = self::STATUS_OVERDUE;
        } else {
            $this->status = self::STATUS_PENDING;
        }

        $this->save();
    }

    public function canReceivePayment()
    {
        return $this->outstanding_amount > 0 &&
            !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }
}
