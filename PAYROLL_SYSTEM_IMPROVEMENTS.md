# 💼 Mejoras al Sistema de Nóminas - Casa Bonita (Microempresa)

**Fecha:** 14 de Noviembre de 2025  
**Objetivo:** Mejorar el cálculo de nóminas con descuentos correctos según normativa peruana para microempresas

---

## 📊 **Estado Actual vs Estado Deseado**

### ❌ **Problemas Actuales:**
1. `social_security` es genérico - no distingue entre AFP/ONP
2. `health_insurance` no se usa correctamente (EsSalud lo paga el empleador)
3. No se guardan los datos de AFP (proveedor, CUSPP)
4. Cálculos simplificados que no reflejan la realidad
5. No hay tabla de parámetros tributarios actualizables

### ✅ **Lo que Necesitamos:**
1. **Campos específicos para AFP:** aporte, comisión, seguro
2. **Campo para ONP** (13%)
3. **EsSalud** como aportación del empleador (informativo)
4. **Impuesto a la Renta** calculado correctamente
5. **Parámetros tributarios** actualizables por año

---

## 🏗️ **1. MODIFICACIONES A LA BD**

### **A. Actualizar tabla `employees`**
```sql
-- Agregar campos de sistema pensionario
ALTER TABLE employees 
ADD COLUMN pension_system ENUM('AFP', 'ONP', 'NINGUNO') DEFAULT 'AFP' AFTER base_salary,
ADD COLUMN afp_provider ENUM('PRIMA', 'INTEGRA', 'PROFUTURO', 'HABITAT') NULL AFTER pension_system,
ADD COLUMN cuspp VARCHAR(13) NULL COMMENT 'Código Único de Seguridad Pensionaria' AFTER afp_provider,
ADD COLUMN has_family_allowance BOOLEAN DEFAULT FALSE AFTER cuspp,
ADD COLUMN number_of_children INT DEFAULT 0 AFTER has_family_allowance;

-- Crear índices
CREATE INDEX idx_employees_pension ON employees(pension_system, afp_provider);
```

### **B. Actualizar tabla `payrolls`**
```sql
-- RENOMBRAR/AGREGAR COLUMNAS PARA CLARIDAD

-- 1. Reemplazar 'social_security' con campos específicos
ALTER TABLE payrolls 
DROP COLUMN social_security,
ADD COLUMN pension_system ENUM('AFP', 'ONP', 'NINGUNO') NOT NULL DEFAULT 'AFP' AFTER gross_salary,
ADD COLUMN afp_provider VARCHAR(50) NULL AFTER pension_system,
ADD COLUMN afp_contribution DECIMAL(10,2) DEFAULT 0 COMMENT 'Aporte AFP 10%' AFTER afp_provider,
ADD COLUMN afp_commission DECIMAL(10,2) DEFAULT 0 COMMENT 'Comisión AFP 1-1.47%' AFTER afp_contribution,
ADD COLUMN afp_insurance DECIMAL(10,2) DEFAULT 0 COMMENT 'Seguro AFP 0.99%' AFTER afp_commission,
ADD COLUMN onp_contribution DECIMAL(10,2) DEFAULT 0 COMMENT 'Aporte ONP 13%' AFTER afp_insurance,
ADD COLUMN total_pension DECIMAL(10,2) DEFAULT 0 COMMENT 'Total Sistema Pensionario' AFTER onp_contribution;

-- 2. Reemplazar 'health_insurance' con campo informativo del empleador
ALTER TABLE payrolls
DROP COLUMN health_insurance,
ADD COLUMN employer_essalud DECIMAL(10,2) DEFAULT 0 COMMENT 'EsSalud pagado por empleador (9% - informativo)' AFTER total_pension;

-- 3. Renombrar 'income_tax' para ser más claro
ALTER TABLE payrolls
CHANGE COLUMN income_tax rent_tax_5th DECIMAL(10,2) DEFAULT 0 COMMENT 'Impuesto a la Renta 5ta categoría';

-- 4. Agregar campo para asignación familiar
ALTER TABLE payrolls
ADD COLUMN family_allowance DECIMAL(10,2) DEFAULT 0 COMMENT 'Asignación Familiar S/ 102.50' AFTER base_salary;

-- 5. Actualizar total_deductions para reflejar los nuevos campos
-- (Se recalculará en el código: total_pension + rent_tax_5th + other_deductions)
```

### **C. Crear tabla `tax_parameters`**
```sql
CREATE TABLE tax_parameters (
    parameter_id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL COMMENT 'Año fiscal',
    
    -- Valores base
    uit_amount DECIMAL(10,2) NOT NULL DEFAULT 5150.00 COMMENT 'Unidad Impositiva Tributaria',
    family_allowance DECIMAL(10,2) NOT NULL DEFAULT 102.50 COMMENT 'Asignación Familiar',
    minimum_wage DECIMAL(10,2) NOT NULL DEFAULT 1025.00 COMMENT 'Remuneración Mínima Vital',
    
    -- AFP - Tasas actualizables
    afp_contribution_rate DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Aporte obligatorio AFP (%)',
    afp_insurance_rate DECIMAL(5,2) DEFAULT 0.99 COMMENT 'Seguro AFP (%)',
    
    afp_prima_commission DECIMAL(5,2) DEFAULT 1.47 COMMENT 'Comisión Prima (%)',
    afp_integra_commission DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Comisión Integra (%)',
    afp_profuturo_commission DECIMAL(5,2) DEFAULT 1.20 COMMENT 'Comisión Profuturo (%)',
    afp_habitat_commission DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Comisión Habitat (%)',
    
    -- ONP
    onp_rate DECIMAL(5,2) DEFAULT 13.00 COMMENT 'Tasa ONP (%)',
    
    -- EsSalud (informativo - pagado por empleador)
    essalud_rate DECIMAL(5,2) DEFAULT 9.00 COMMENT 'Aporte EsSalud del empleador (%)',
    
    -- Impuesto a la Renta - Tramos (en UIT)
    rent_tax_deduction_uit DECIMAL(5,2) DEFAULT 7.00 COMMENT 'Deducción anual en UIT',
    rent_tax_tramo1_uit DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Hasta 5 UIT',
    rent_tax_tramo1_rate DECIMAL(5,2) DEFAULT 8.00 COMMENT 'Tasa tramo 1 (%)',
    rent_tax_tramo2_uit DECIMAL(5,2) DEFAULT 20.00 COMMENT 'De 5 a 20 UIT',
    rent_tax_tramo2_rate DECIMAL(5,2) DEFAULT 14.00 COMMENT 'Tasa tramo 2 (%)',
    rent_tax_tramo3_uit DECIMAL(5,2) DEFAULT 35.00 COMMENT 'De 20 a 35 UIT',
    rent_tax_tramo3_rate DECIMAL(5,2) DEFAULT 17.00 COMMENT 'Tasa tramo 3 (%)',
    rent_tax_tramo4_uit DECIMAL(5,2) DEFAULT 45.00 COMMENT 'De 35 a 45 UIT',
    rent_tax_tramo4_rate DECIMAL(5,2) DEFAULT 20.00 COMMENT 'Tasa tramo 4 (%)',
    rent_tax_tramo5_rate DECIMAL(5,2) DEFAULT 30.00 COMMENT 'Más de 45 UIT (%)',
    
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar parámetros para 2025
INSERT INTO tax_parameters (
    year, uit_amount, family_allowance, minimum_wage,
    afp_contribution_rate, afp_insurance_rate,
    afp_prima_commission, afp_integra_commission, afp_profuturo_commission, afp_habitat_commission,
    onp_rate, essalud_rate
) VALUES (
    2025, 5150.00, 102.50, 1025.00,
    10.00, 0.99,
    1.47, 1.00, 1.20, 1.00,
    13.00, 9.00
);
```

---

## 💻 **2. ACTUALIZAR MODELO `Payroll.php`**

```php
<?php
// Modules/HumanResources/app/Models/Payroll.php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $primaryKey = 'payroll_id';

    protected $fillable = [
        'employee_id',
        'payroll_period',
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        
        // Ingresos
        'base_salary',
        'family_allowance',
        'commissions_amount',
        'bonuses_amount',
        'overtime_amount',
        'other_income',
        'gross_salary',
        
        // Sistema Pensionario
        'pension_system',
        'afp_provider',
        'afp_contribution',
        'afp_commission',
        'afp_insurance',
        'onp_contribution',
        'total_pension',
        
        // Impuesto a la Renta
        'rent_tax_5th',
        
        // Otros
        'other_deductions',
        'total_deductions',
        
        // Aportaciones del Empleador (informativo)
        'employer_essalud',
        
        // Neto
        'net_salary',
        
        'currency',
        'status',
        'processed_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'pay_date' => 'date',
        'base_salary' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'commissions_amount' => 'decimal:2',
        'bonuses_amount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'other_income' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'afp_contribution' => 'decimal:2',
        'afp_commission' => 'decimal:2',
        'afp_insurance' => 'decimal:2',
        'onp_contribution' => 'decimal:2',
        'total_pension' => 'decimal:2',
        'rent_tax_5th' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'employer_essalud' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    // Relaciones
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function processor()
    {
        return $this->belongsTo(Employee::class, 'processed_by', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    // Scopes
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('payroll_period', $period);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
```

---

## 🔧 **3. CREAR MODELO `TaxParameter.php`**

```php
<?php
// Modules/HumanResources/app/Models/TaxParameter.php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;

class TaxParameter extends Model
{
    protected $primaryKey = 'parameter_id';

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
        'is_active'
    ];

    protected $casts = [
        'year' => 'integer',
        'uit_amount' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'minimum_wage' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    // Obtener parámetros activos de un año
    public static function getActiveForYear(int $year)
    {
        return self::where('year', $year)
            ->where('is_active', true)
            ->firstOrFail();
    }

    // Obtener parámetros del año actual
    public static function getCurrent()
    {
        return self::getActiveForYear(date('Y'));
    }
}
```

---

## 💪 **4. SERVICIO MEJORADO `PayrollCalculationService.php`**

```php
<?php
// Modules/HumanResources/app/Services/PayrollCalculationService.php

namespace Modules\HumanResources\Services;

use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Payroll;
use Modules\HumanResources\Models\TaxParameter;
use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;

class PayrollCalculationService
{
    /**
     * Calcular nómina completa para un empleado
     */
    public function calculatePayroll(
        int $employeeId,
        int $month,
        int $year,
        array $additionalData = []
    ): Payroll {
        // 1. Obtener empleado
        $employee = Employee::with(['user'])->findOrFail($employeeId);
        
        // 2. Obtener parámetros tributarios
        $taxParams = TaxParameter::getActiveForYear($year);
        
        // 3. Definir período
        $period = sprintf('%04d-%02d', $year, $month);
        $payPeriodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $payPeriodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $payDate = $additionalData['pay_date'] ?? $payPeriodEnd->copy()->addDays(5);
        
        // 4. Calcular días trabajados
        $workedDays = $additionalData['worked_days'] ?? 30;
        $totalDays = $additionalData['total_days'] ?? 30;
        
        // 5. ===== CALCULAR INGRESOS =====
        
        // Sueldo básico proporcional
        $baseSalary = ($employee->base_salary / 30) * $workedDays;
        
        // Asignación familiar (solo si tiene hijos menores de 18 años)
        $familyAllowance = 0;
        if ($employee->has_family_allowance && $employee->number_of_children > 0) {
            $familyAllowance = $taxParams->family_allowance;
        }
        
        // Comisiones del mes (desde sistema de comisiones)
        $commissionsAmount = $this->getMonthlyCommissions($employeeId, $month, $year);
        
        // Bonos adicionales
        $bonusesAmount = $additionalData['bonuses_amount'] ?? 0;
        
        // Horas extras
        $overtimeAmount = $additionalData['overtime_amount'] ?? 0;
        
        // Otros ingresos
        $otherIncome = $additionalData['other_income'] ?? 0;
        
        // Total bruto
        $grossSalary = $baseSalary + $familyAllowance + $commissionsAmount + 
                      $bonusesAmount + $overtimeAmount + $otherIncome;
        
        // 6. ===== CALCULAR DESCUENTOS =====
        
        // Sistema Pensionario (AFP u ONP)
        $pensionData = $this->calculatePension(
            $grossSalary,
            $employee->pension_system,
            $employee->afp_provider,
            $taxParams
        );
        
        // Impuesto a la Renta (5ta categoría)
        $rentTax = $this->calculateIncomeTax($grossSalary, $month, $year, $taxParams);
        
        // Otros descuentos (préstamos, adelantos)
        $otherDeductions = $additionalData['other_deductions'] ?? 0;
        
        // Total descuentos
        $totalDeductions = $pensionData['total'] + $rentTax + $otherDeductions;
        
        // 7. ===== NETO A PAGAR =====
        $netSalary = $grossSalary - $totalDeductions;
        
        // 8. ===== APORTACIONES DEL EMPLEADOR (informativo) =====
        $employerEssalud = $grossSalary * ($taxParams->essalud_rate / 100);
        
        // 9. ===== GUARDAR O ACTUALIZAR NÓMINA =====
        $payroll = Payroll::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'payroll_period' => $period
            ],
            [
                'pay_period_start' => $payPeriodStart,
                'pay_period_end' => $payPeriodEnd,
                'pay_date' => $payDate,
                
                // Ingresos
                'base_salary' => round($baseSalary, 2),
                'family_allowance' => round($familyAllowance, 2),
                'commissions_amount' => round($commissionsAmount, 2),
                'bonuses_amount' => round($bonusesAmount, 2),
                'overtime_amount' => round($overtimeAmount, 2),
                'other_income' => round($otherIncome, 2),
                'gross_salary' => round($grossSalary, 2),
                
                // Sistema Pensionario
                'pension_system' => $employee->pension_system,
                'afp_provider' => $employee->afp_provider,
                'afp_contribution' => round($pensionData['contribution'], 2),
                'afp_commission' => round($pensionData['commission'], 2),
                'afp_insurance' => round($pensionData['insurance'], 2),
                'onp_contribution' => round($pensionData['onp'], 2),
                'total_pension' => round($pensionData['total'], 2),
                
                // Impuesto a la Renta
                'rent_tax_5th' => round($rentTax, 2),
                
                // Otros
                'other_deductions' => round($otherDeductions, 2),
                'total_deductions' => round($totalDeductions, 2),
                
                // Aportaciones del Empleador
                'employer_essalud' => round($employerEssalud, 2),
                
                // Neto
                'net_salary' => round($netSalary, 2),
                
                'currency' => 'PEN',
                'status' => 'borrador',
                'notes' => $additionalData['notes'] ?? null
            ]
        );
        
        return $payroll->load('employee.user');
    }
    
    /**
     * Calcular descuento de AFP u ONP
     */
    private function calculatePension(
        float $grossSalary,
        string $system,
        ?string $provider,
        TaxParameter $taxParams
    ): array {
        $result = [
            'contribution' => 0,
            'commission' => 0,
            'insurance' => 0,
            'onp' => 0,
            'total' => 0
        ];
        
        if ($system === 'NINGUNO') {
            return $result;
        }
        
        if ($system === 'ONP') {
            $result['onp'] = $grossSalary * ($taxParams->onp_rate / 100);
            $result['total'] = $result['onp'];
            return $result;
        }
        
        // AFP
        $result['contribution'] = $grossSalary * ($taxParams->afp_contribution_rate / 100); // 10%
        
        // Comisión según proveedor
        $commissionRate = match($provider) {
            'PRIMA' => $taxParams->afp_prima_commission,
            'INTEGRA' => $taxParams->afp_integra_commission,
            'PROFUTURO' => $taxParams->afp_profuturo_commission,
            'HABITAT' => $taxParams->afp_habitat_commission,
            default => 1.20
        };
        
        $result['commission'] = $grossSalary * ($commissionRate / 100);
        $result['insurance'] = $grossSalary * ($taxParams->afp_insurance_rate / 100);
        $result['total'] = $result['contribution'] + $result['commission'] + $result['insurance'];
        
        return $result;
    }
    
    /**
     * Calcular Impuesto a la Renta (5ta categoría)
     * Para microempresas: Solo si el ingreso anual proyectado supera 7 UIT
     */
    private function calculateIncomeTax(
        float $grossSalary,
        int $month,
        int $year,
        TaxParameter $taxParams
    ): float {
        // Proyección anual simple (asumiendo ingreso constante)
        $annualIncome = $grossSalary * 12;
        
        // Deducción: 7 UIT anuales
        $deduction = $taxParams->rent_tax_deduction_uit * $taxParams->uit_amount;
        
        // Si no supera el mínimo, no paga impuesto
        if ($annualIncome <= $deduction) {
            return 0;
        }
        
        $taxableIncome = $annualIncome - $deduction;
        $annualTax = 0;
        $uit = $taxParams->uit_amount;
        
        // Tramos (2025)
        if ($taxableIncome <= $taxParams->rent_tax_tramo1_uit * $uit) {
            // Hasta 5 UIT: 8%
            $annualTax = $taxableIncome * ($taxParams->rent_tax_tramo1_rate / 100);
        } 
        elseif ($taxableIncome <= $taxParams->rent_tax_tramo2_uit * $uit) {
            // De 5 a 20 UIT: 14%
            $tramo1 = $taxParams->rent_tax_tramo1_uit * $uit * ($taxParams->rent_tax_tramo1_rate / 100);
            $exceso = $taxableIncome - ($taxParams->rent_tax_tramo1_uit * $uit);
            $annualTax = $tramo1 + ($exceso * ($taxParams->rent_tax_tramo2_rate / 100));
        } 
        elseif ($taxableIncome <= $taxParams->rent_tax_tramo3_uit * $uit) {
            // De 20 a 35 UIT: 17%
            $tramo1 = $taxParams->rent_tax_tramo1_uit * $uit * ($taxParams->rent_tax_tramo1_rate / 100);
            $tramo2 = ($taxParams->rent_tax_tramo2_uit - $taxParams->rent_tax_tramo1_uit) * $uit * ($taxParams->rent_tax_tramo2_rate / 100);
            $exceso = $taxableIncome - ($taxParams->rent_tax_tramo2_uit * $uit);
            $annualTax = $tramo1 + $tramo2 + ($exceso * ($taxParams->rent_tax_tramo3_rate / 100));
        } 
        elseif ($taxableIncome <= $taxParams->rent_tax_tramo4_uit * $uit) {
            // De 35 a 45 UIT: 20%
            $tramo1 = $taxParams->rent_tax_tramo1_uit * $uit * ($taxParams->rent_tax_tramo1_rate / 100);
            $tramo2 = ($taxParams->rent_tax_tramo2_uit - $taxParams->rent_tax_tramo1_uit) * $uit * ($taxParams->rent_tax_tramo2_rate / 100);
            $tramo3 = ($taxParams->rent_tax_tramo3_uit - $taxParams->rent_tax_tramo2_uit) * $uit * ($taxParams->rent_tax_tramo3_rate / 100);
            $exceso = $taxableIncome - ($taxParams->rent_tax_tramo3_uit * $uit);
            $annualTax = $tramo1 + $tramo2 + $tramo3 + ($exceso * ($taxParams->rent_tax_tramo4_rate / 100));
        } 
        else {
            // Más de 45 UIT: 30%
            $tramo1 = $taxParams->rent_tax_tramo1_uit * $uit * ($taxParams->rent_tax_tramo1_rate / 100);
            $tramo2 = ($taxParams->rent_tax_tramo2_uit - $taxParams->rent_tax_tramo1_uit) * $uit * ($taxParams->rent_tax_tramo2_rate / 100);
            $tramo3 = ($taxParams->rent_tax_tramo3_uit - $taxParams->rent_tax_tramo2_uit) * $uit * ($taxParams->rent_tax_tramo3_rate / 100);
            $tramo4 = ($taxParams->rent_tax_tramo4_uit - $taxParams->rent_tax_tramo3_uit) * $uit * ($taxParams->rent_tax_tramo4_rate / 100);
            $exceso = $taxableIncome - ($taxParams->rent_tax_tramo4_uit * $uit);
            $annualTax = $tramo1 + $tramo2 + $tramo3 + $tramo4 + ($exceso * ($taxParams->rent_tax_tramo5_rate / 100));
        }
        
        // Mensualizar
        return $annualTax / 12;
    }
    
    /**
     * Obtener comisiones aprobadas del mes
     */
    private function getMonthlyCommissions(int $employeeId, int $month, int $year): float
    {
        return Commission::where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('payment_status', 'pagado')
            ->sum('commission_amount');
    }
    
    /**
     * Calcular nómina masiva para todos los empleados activos
     */
    public function calculateMassPayroll(int $month, int $year): array
    {
        $employees = Employee::where('employment_status', 'activo')->get();
        $results = [
            'success' => [],
            'errors' => [],
            'total' => $employees->count()
        ];
        
        foreach ($employees as $employee) {
            try {
                $payroll = $this->calculatePayroll($employee->employee_id, $month, $year);
                $results['success'][] = [
                    'employee_id' => $employee->employee_id,
                    'employee_name' => $employee->user->first_name . ' ' . $employee->user->last_name,
                    'payroll_id' => $payroll->payroll_id,
                    'net_salary' => $payroll->net_salary
                ];
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'employee_id' => $employee->employee_id,
                    'employee_name' => $employee->user->first_name . ' ' . $employee->user->last_name,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}
```

---

## 🎯 **5. RESUMEN DE CÁLCULOS**

### **Ejemplo Práctico:**

**Empleado:** Juan Pérez  
**Sueldo Base:** S/ 2,500.00  
**Comisiones:** S/ 500.00  
**Sistema:** AFP Integra  
**Hijos:** 1 (menor de 18)

```
INGRESOS:
├─ Sueldo Básico: S/ 2,500.00
├─ Asignación Familiar: S/ 102.50
├─ Comisiones: S/ 500.00
└─ BRUTO: S/ 3,102.50

DESCUENTOS:
├─ AFP Integra:
│  ├─ Aporte (10%): S/ 310.25
│  ├─ Comisión (1.00%): S/ 31.03
│  └─ Seguro (0.99%): S/ 30.71
│  └─ Total: S/ 371.99
│
├─ Impuesto Renta:
│  ├─ Proyección anual: S/ 37,230.00
│  ├─ Deducción (7 UIT): S/ 36,050.00
│  ├─ Base imponible: S/ 1,180.00
│  ├─ Tramo 8%: S/ 94.40 anual
│  └─ Mensual: S/ 7.87
│
└─ TOTAL DESCUENTOS: S/ 379.86

APORTACIONES EMPLEADOR (informativo):
└─ EsSalud (9%): S/ 279.23

NETO A PAGAR: S/ 2,722.64
```

---

## 📱 **6. INTERFAZ FRONTEND**

### **Actualizar modelo TypeScript:**

```typescript
// src/app/modules/humanResources/models/payroll.ts
export interface Payroll {
  payroll_id: number;
  employee_id: number;
  payroll_period: string;
  pay_period_start: string;
  pay_period_end: string;
  pay_date: string;

  // Ingresos
  base_salary: number;
  family_allowance: number;
  commissions_amount: number;
  bonuses_amount: number;
  overtime_amount: number;
  other_income: number;
  gross_salary: number;

  // Sistema Pensionario
  pension_system: 'AFP' | 'ONP' | 'NINGUNO';
  afp_provider?: 'PRIMA' | 'INTEGRA' | 'PROFUTURO' | 'HABITAT';
  afp_contribution: number;
  afp_commission: number;
  afp_insurance: number;
  onp_contribution: number;
  total_pension: number;

  // Impuesto a la Renta
  rent_tax_5th: number;

  // Otros descuentos
  other_deductions: number;
  total_deductions: number;

  // Aportaciones del Empleador (informativo)
  employer_essalud: number;

  // Neto
  net_salary: number;

  currency: string;
  status: 'borrador' | 'procesado' | 'aprobado' | 'pagado' | 'cancelado';
  notes?: string;
  
  // Relaciones
  employee?: any;
  
  // Timestamps
  created_at?: string;
  updated_at?: string;
  approved_at?: string;
}
```

---

## ✅ **CHECKLIST DE IMPLEMENTACIÓN**

### **Backend:**
- [ ] Ejecutar migraciones para actualizar tablas
- [ ] Crear tabla `tax_parameters`
- [ ] Crear modelo `TaxParameter.php`
- [ ] Actualizar modelo `Payroll.php`
- [ ] Actualizar modelo `Employee.php`
- [ ] Crear servicio `PayrollCalculationService.php`
- [ ] Actualizar controlador `PayrollController.php`
- [ ] Crear seeder para parámetros tributarios 2025
- [ ] Actualizar `PayrollResource.php` para devolver nuevos campos

### **Frontend:**
- [ ] Actualizar interfaz `Payroll` en TypeScript
- [ ] Actualizar vista de boleta (`payroll-view.component`)
- [ ] Mostrar desglose AFP/ONP por separado
- [ ] Mostrar EsSalud como aporte del empleador
- [ ] Actualizar formulario de empleados con campos de AFP/ONP
- [ ] Agregar selector de AFP en formulario de empleado

### **Testing:**
- [ ] Probar cálculo con AFP Prima
- [ ] Probar cálculo con AFP Integra
- [ ] Probar cálculo con AFP Profuturo
- [ ] Probar cálculo con AFP Habitat
- [ ] Probar cálculo con ONP
- [ ] Probar con/sin asignación familiar
- [ ] Probar cálculo de impuesto a la renta
- [ ] Verificar totales en reportes

---

## 🚀 **PRÓXIMOS PASOS**

1. **Ejecutar migraciones** en producción
2. **Insertar parámetros** del 2025
3. **Actualizar empleados** con su sistema pensionario
4. **Recalcular nóminas** del mes actual
5. **Generar reportes** actualizados
6. **Capacitar** al equipo de RR.HH.

---

**✨ Con esto tendrán un sistema de nóminas 100% legal y correcto para microempresas en Perú!**

*Última actualización: 14 de Noviembre de 2025*
