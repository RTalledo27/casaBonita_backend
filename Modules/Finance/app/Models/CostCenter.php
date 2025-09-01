<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Security\Models\User;

// use Modules\Finance\Database\Factories\CostCenterFactory;

class CostCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'cost_center_id';

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'code', 'name', 'description', 'parent_id',
        'manager_id', 'is_active'
    ];

    /**
     * Casting de tipos
     */
    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Valores por defecto
     */
    protected $attributes = [
        'is_active' => true
    ];

    /**
     * Relación: Centro de costo padre (para jerarquías)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    /**
     * Relación: Centros de costo hijos
     */
    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    /**
     * Relación: Gerente responsable del centro de costo
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Relación: Líneas de presupuesto asociadas
     */
    public function budgetLines()
    {
        return $this->hasMany(BudgetLine::class, 'department', 'code');
    }

    /**
     * Relación: Flujos de caja asociados
     */
    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class);
    }

    /**
     * Scope: Filtrar centros activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filtrar por tipo
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Obtener solo centros padre (sin padre)
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Obtener solo centros hijos
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Accessor: Verificar si tiene centros hijos
     */
    public function getHasChildrenAttribute()
    {
        return $this->children()->exists();
    }

    /**
     * Accessor: Obtener ruta completa del centro de costo
     */
    public function getFullPathAttribute()
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Accessor: Calcular gasto actual del centro de costo
     */
    public function getActualSpendingAttribute()
    {
        // Sumar gastos de flujos de caja confirmados
        return $this->cashFlows()
            ->where('type', 'OUTFLOW')
            ->where('status', 'CONFIRMED')
            ->sum('actual_amount');
    }

    /**
     * Accessor: Calcular porcentaje de ejecución del presupuesto
     */
    public function getBudgetExecutionPercentageAttribute()
    {
        if ($this->budget_amount == 0) return 0;
        return round(($this->actual_spending / $this->budget_amount) * 100, 2);
    }

    /**
     * Accessor: Verificar si está sobre presupuesto
     */
    public function getIsOverBudgetAttribute()
    {
        return $this->actual_spending > $this->budget_amount;
    }

    /**
     * Accessor: Calcular ingreso total del centro de costo
     */
    public function getTotalIncomeAttribute(): float
    {
        return $this->cashFlows()->income()->sum('amount');
    }

    /**
     * Accessor: Calcular gasto total del centro de costo
     */
    public function getTotalExpenseAttribute(): float
    {
        return $this->cashFlows()->expense()->sum('amount');
    }

    /**
     * Accessor: Calcular flujo de caja neto del centro de costo
     */
    public function getNetCashFlowAttribute(): float
    {
        return $this->getTotalIncomeAttribute() - $this->getTotalExpenseAttribute();
    }

    /**
     * Método: Obtener todos los descendientes (recursivo)
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    /**
     * Método: Calcular gasto total incluyendo centros hijos
     */
    public function getTotalSpendingWithChildren()
    {
        $totalSpending = $this->actual_spending;
        
        foreach ($this->children as $child) {
            $totalSpending += $child->getTotalSpendingWithChildren();
        }

        return $totalSpending;
    }

    /**
     * Método estático: Generar código automático
     */
    public static function generateCode($type = 'DEPARTMENT')
    {
        $prefix = $type === 'PROJECT' ? 'PRJ' : 'DEP';
        $lastCenter = static::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        if (!$lastCenter) {
            return $prefix . '001';
        }

        $lastNumber = intval(substr($lastCenter->code, 3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

}
