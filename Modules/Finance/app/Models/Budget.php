<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Security\Models\User;

// use Modules\Finance\Database\Factories\BudgetFactory;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'fiscal_year',
        'start_date',
        'end_date',
        'total_amount',
        'status',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalExecutedAttribute(): float
    {
        return $this->budgetLines->sum('executed_amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->total_amount - $this->getTotalExecutedAttribute();
    }

    public function getExecutionPercentageAttribute(): float
    {
        if ($this->total_amount == 0) {
            return 0;
        }
        return ($this->getTotalExecutedAttribute() / $this->total_amount) * 100;
    }
}
