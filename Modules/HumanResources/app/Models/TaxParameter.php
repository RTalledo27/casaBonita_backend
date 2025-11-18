<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modelo de Parámetros Tributarios
 * 
 * Almacena valores dinámicos por año para cálculo de planillas:
 * - UIT, RMV, Asignación Familiar
 * - Tasas AFP (aporte, seguro, comisiones)
 * - Tasa ONP
 * - Tasa EsSalud
 * - Tramos de Impuesto a la Renta
 * 
 * @property int $parameter_id
 * @property int $year
 * @property float $uit_amount
 * @property float $family_allowance
 * @property float $minimum_wage
 * @property float $afp_contribution_rate
 * @property float $afp_insurance_rate
 * @property float $afp_prima_commission
 * @property float $afp_integra_commission
 * @property float $afp_profuturo_commission
 * @property float $afp_habitat_commission
 * @property float $onp_rate
 * @property float $essalud_rate
 * @property float $rent_tax_deduction_uit
 * @property float $rent_tax_tramo1_uit
 * @property float $rent_tax_tramo1_rate
 * @property float $rent_tax_tramo2_uit
 * @property float $rent_tax_tramo2_rate
 * @property float $rent_tax_tramo3_uit
 * @property float $rent_tax_tramo3_rate
 * @property float $rent_tax_tramo4_uit
 * @property float $rent_tax_tramo4_rate
 * @property float $rent_tax_tramo5_rate
 * @property bool $is_active
 */
class TaxParameter extends Model
{
    use HasFactory;

    protected $table = 'tax_parameters';
    protected $primaryKey = 'parameter_id';
    public $timestamps = true;

    protected $fillable = [
        'year',
        'uit_amount',
        'family_allowance',
        'minimum_wage',
        'afp_contribution_rate',
        'afp_insurance_rate',
        'afp_prima_commission',
        'afp_integra_commission',
        'afp_profuturo_commission',
        'afp_habitat_commission',
        'onp_rate',
        'essalud_rate',
        'rent_tax_deduction_uit',
        'rent_tax_tramo1_uit',
        'rent_tax_tramo1_rate',
        'rent_tax_tramo2_uit',
        'rent_tax_tramo2_rate',
        'rent_tax_tramo3_uit',
        'rent_tax_tramo3_rate',
        'rent_tax_tramo4_uit',
        'rent_tax_tramo4_rate',
        'rent_tax_tramo5_rate',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'uit_amount' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'minimum_wage' => 'decimal:2',
        'afp_contribution_rate' => 'decimal:2',
        'afp_insurance_rate' => 'decimal:2',
        'afp_prima_commission' => 'decimal:2',
        'afp_integra_commission' => 'decimal:2',
        'afp_profuturo_commission' => 'decimal:2',
        'afp_habitat_commission' => 'decimal:2',
        'onp_rate' => 'decimal:2',
        'essalud_rate' => 'decimal:2',
        'rent_tax_deduction_uit' => 'decimal:2',
        'rent_tax_tramo1_uit' => 'decimal:2',
        'rent_tax_tramo1_rate' => 'decimal:2',
        'rent_tax_tramo2_uit' => 'decimal:2',
        'rent_tax_tramo2_rate' => 'decimal:2',
        'rent_tax_tramo3_uit' => 'decimal:2',
        'rent_tax_tramo3_rate' => 'decimal:2',
        'rent_tax_tramo4_uit' => 'decimal:2',
        'rent_tax_tramo4_rate' => 'decimal:2',
        'rent_tax_tramo5_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener parámetros activos para un año específico
     * 
     * @param int $year
     * @return TaxParameter|null
     */
    public static function getActiveForYear(int $year): ?TaxParameter
    {
        return static::where('year', $year)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Obtener parámetros del año actual
     * 
     * @return TaxParameter|null
     */
    public static function getCurrent(): ?TaxParameter
    {
        $currentYear = date('Y');
        return static::getActiveForYear($currentYear);
    }

    /**
     * Obtener todos los años disponibles
     * 
     * @return array
     */
    public static function getAvailableYears(): array
    {
        return static::where('is_active', true)
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
    }

    /**
     * Calcular asignación familiar (10% del RMV)
     * 
     * @param float $minimumWage
     * @return float
     */
    public static function calculateFamilyAllowance(float $minimumWage): float
    {
        return round($minimumWage * 0.10, 2);
    }

    /**
     * Validar que todos los valores estén configurados
     * 
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->uit_amount > 0
            && $this->family_allowance > 0
            && $this->minimum_wage > 0
            && $this->afp_contribution_rate > 0
            && $this->onp_rate > 0
            && $this->essalud_rate > 0;
    }

    /**
     * Scope para obtener solo parámetros activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para obtener por año
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
