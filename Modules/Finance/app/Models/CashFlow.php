<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\User;

// use Modules\Finance\Database\Factories\CashFlowFactory;

class CashFlow extends Model
{
    use HasFactory;
    protected $table = 'cash_flows';

    protected $fillable = [
        'date',
        'description',
        'category',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'cost_center_id',
        'created_by'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2'
    ];

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    const CATEGORY_OPERATIONS = 'operations';
    const CATEGORY_INVESTMENTS = 'investments';
    const CATEGORY_FINANCING = 'financing';

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

}
