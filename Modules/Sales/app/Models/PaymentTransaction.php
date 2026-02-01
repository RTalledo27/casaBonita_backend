<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Sales\Models\Contract;
use Modules\Collections\Models\PaymentSchedule;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'contract_id',
        'start_schedule_id',
        'payment_date',
        'amount_total',
        'method',
        'reference',
        'voucher_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_total' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function startSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'start_schedule_id', 'schedule_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'transaction_id', 'transaction_id');
    }
}

