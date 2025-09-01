<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccount;

// use Modules\Finance\Database\Factories\BudgetLineFactory;

class BudgetLine extends Model
{
    use HasFactory;
    protected $fillable = [
        'budget_id',
        'account_id',
        'description',
        'budgeted_amount',
        'executed_amount',
        'quarter_1',
        'quarter_2',
        'quarter_3',
        'quarter_4'
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:2',
        'executed_amount' => 'decimal:2',
        'quarter_1' => 'decimal:2',
        'quarter_2' => 'decimal:2',
        'quarter_3' => 'decimal:2',
        'quarter_4' => 'decimal:2'
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->budgeted_amount - $this->executed_amount;
    }

    public function getExecutionPercentageAttribute(): float
    {
        if ($this->budgeted_amount == 0) {
            return 0;
        }
        return ($this->executed_amount / $this->budgeted_amount) * 100;
    }

}
