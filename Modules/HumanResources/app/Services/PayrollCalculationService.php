<?php

namespace Modules\HumanResources\Services;

use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\TaxParameter;
use Carbon\Carbon;

/**
 * Servicio de Cálculo de Planillas
 * 
 * Calcula todos los componentes de una planilla usando parámetros tributarios dinámicos.
 * Compatible con el régimen de microempresa en Perú (sin CTS ni gratificaciones).
 * 
 * @package Modules\HumanResources\Services
 */
class PayrollCalculationService
{
    /**
     * Calcular planilla completa para un empleado
     * 
     * @param Employee $employee Empleado
     * @param float $baseSalary Salario base del período
     * @param float $commissionsAmount Comisiones del período
     * @param float $bonusesAmount Bonos del período
     * @param float $overtimeAmount Horas extras del período
     * @param int $year Año para obtener parámetros tributarios
     * @return array Datos calculados de la planilla
     */
    public function calculatePayroll(
        Employee $employee,
        float $baseSalary,
        float $commissionsAmount = 0,
        float $bonusesAmount = 0,
        float $overtimeAmount = 0,
        ?int $year = null
    ): array {
        // Obtener año actual si no se especifica
        $year = $year ?? Carbon::now()->year;
        
        // Obtener parámetros tributarios del año
        $taxParams = TaxParameter::getActiveForYear($year);
        
        if (!$taxParams) {
            throw new \Exception("No existen parámetros tributarios configurados para el año {$year}");
        }

        // 1. CALCULAR ASIGNACIÓN FAMILIAR (si aplica)
        $familyAllowance = 0;
        if ($employee->has_family_allowance && $employee->number_of_children > 0) {
            $familyAllowance = $taxParams->family_allowance;
        }

        // 2. CALCULAR SALARIO BRUTO
        $grossSalary = $baseSalary + $commissionsAmount + $bonusesAmount + $overtimeAmount + $familyAllowance;

        // 3. CALCULAR SISTEMA DE PENSIONES (AFP o ONP)
        $pensionData = $this->calculatePensionSystem($employee, $grossSalary, $taxParams);

        // 4. CALCULAR IMPUESTO A LA RENTA (5ta categoría)
        $rentTax5th = $this->calculateIncomeTax($grossSalary, $taxParams, $pensionData['total_pension']);

        // 5. CALCULAR ESSALUD
        $employeeEssalud = $this->calculateEmployeeEssalud($grossSalary, $taxParams); // Descuento del trabajador (9%)
        $employerEssalud = $this->calculateEmployerEssalud($grossSalary, $taxParams); // Aporte del empleador (9%)

        // 6. CALCULAR DEDUCCIONES TOTALES
        $totalDeductions = $pensionData['total_pension'] + $employeeEssalud + $rentTax5th;

        // 7. CALCULAR SALARIO NETO
        $netSalary = $grossSalary - $totalDeductions;

        // Retornar todos los datos calculados
        return [
            // Ingresos
            'base_salary' => round($baseSalary, 2),
            'commissions_amount' => round($commissionsAmount, 2),
            'bonuses_amount' => round($bonusesAmount, 2),
            'overtime_amount' => round($overtimeAmount, 2),
            'family_allowance' => round($familyAllowance, 2),
            'gross_salary' => round($grossSalary, 2),
            
            // Sistema de Pensiones
            'pension_system' => $pensionData['system'],
            'afp_provider' => $pensionData['afp_provider'],
            'afp_contribution' => round($pensionData['afp_contribution'], 2),
            'afp_commission' => round($pensionData['afp_commission'], 2),
            'afp_insurance' => round($pensionData['afp_insurance'], 2),
            'onp_contribution' => round($pensionData['onp_contribution'], 2),
            'total_pension' => round($pensionData['total_pension'], 2),
            
            // Impuestos y Seguros
            'employee_essalud' => round($employeeEssalud, 2),
            'rent_tax_5th' => round($rentTax5th, 2),
            
            // Totales
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2),
            
            // Empleador (informativo)
            'employer_essalud' => round($employerEssalud, 2),
        ];
    }

    /**
     * Calcular sistema de pensiones (AFP o ONP)
     * 
     * @param Employee $employee
     * @param float $grossSalary
     * @param TaxParameter $taxParams
     * @return array
     */
    protected function calculatePensionSystem(Employee $employee, float $grossSalary, TaxParameter $taxParams): array
    {
        $result = [
            'system' => null,
            'afp_provider' => null,
            'afp_contribution' => 0,
            'afp_commission' => 0,
            'afp_insurance' => 0,
            'onp_contribution' => 0,
            'total_pension' => 0,
        ];

        // Si no tiene sistema de pensiones definido, retornar vacío
        if (!$employee->pension_system) {
            return $result;
        }

        $result['system'] = $employee->pension_system;

        if (strtolower($employee->pension_system) === 'afp') {
            // ===== CÁLCULO AFP =====
            $result['afp_provider'] = $employee->afp_provider;

            // 1. Aporte AFP (10% del salario bruto)
            $result['afp_contribution'] = $grossSalary * ($taxParams->afp_contribution_rate / 100);

            // 2. Seguro AFP (0.99% del salario bruto)
            $result['afp_insurance'] = $grossSalary * ($taxParams->afp_insurance_rate / 100);

            // 3. Comisión AFP (variable según proveedor)
            $commissionRate = $this->getAfpCommissionRate($employee->afp_provider, $taxParams);
            $result['afp_commission'] = $grossSalary * ($commissionRate / 100);

            // Total AFP
            $result['total_pension'] = $result['afp_contribution'] + $result['afp_insurance'] + $result['afp_commission'];

        } elseif (strtolower($employee->pension_system) === 'onp') {
            // ===== CÁLCULO ONP =====
            // ONP: 13% del salario bruto
            $result['onp_contribution'] = $grossSalary * ($taxParams->onp_rate / 100);
            $result['total_pension'] = $result['onp_contribution'];
        }

        return $result;
    }

    /**
     * Obtener tasa de comisión AFP según proveedor
     * 
     * @param string|null $provider
     * @param TaxParameter $taxParams
     * @return float
     */
    protected function getAfpCommissionRate(?string $provider, TaxParameter $taxParams): float
    {
        if (!$provider) {
            return 0;
        }

        return match (strtolower($provider)) {
            'prima' => $taxParams->afp_prima_commission,
            'integra' => $taxParams->afp_integra_commission,
            'profuturo' => $taxParams->afp_profuturo_commission,
            'habitat' => $taxParams->afp_habitat_commission,
            default => 0,
        };
    }

    /**
     * Calcular Impuesto a la Renta de 5ta Categoría
     * 
     * Usa tabla progresiva de 5 tramos en UIT
     * 
     * @param float $grossSalary Salario bruto mensual
     * @param TaxParameter $taxParams Parámetros tributarios
     * @param float $pensionDeduction Deducción de pensiones
     * @return float Impuesto mensual
     */
    protected function calculateIncomeTax(float $grossSalary, TaxParameter $taxParams, float $pensionDeduction): float
    {
        // Proyectar ingreso anual (salario mensual × 12)
        $annualGrossSalary = $grossSalary * 12;
        
        // Deducir aporte de pensiones anual
        $annualPensionDeduction = $pensionDeduction * 12;
        
        // Calcular renta neta anual
        $annualNetIncome = $annualGrossSalary - $annualPensionDeduction;
        
        // Deducir 7 UIT (deducción estándar)
        $deductionAmount = $taxParams->uit_amount * $taxParams->rent_tax_deduction_uit;
        $taxableIncome = max(0, $annualNetIncome - $deductionAmount);
        
        // Si no hay renta gravable, no hay impuesto
        if ($taxableIncome <= 0) {
            return 0;
        }

        // Convertir renta gravable a UIT
        $taxableIncomeInUit = $taxableIncome / $taxParams->uit_amount;

        // Calcular impuesto por tramos progresivos
        $annualTax = 0;

        // TRAMO 1: Hasta 5 UIT → 8%
        if ($taxableIncomeInUit > 0) {
            $tramo1Limit = $taxParams->rent_tax_tramo1_uit;
            $tramo1Amount = min($taxableIncomeInUit, $tramo1Limit);
            $annualTax += ($tramo1Amount * $taxParams->uit_amount) * ($taxParams->rent_tax_tramo1_rate / 100);
            
            if ($taxableIncomeInUit <= $tramo1Limit) {
                return round($annualTax / 12, 2); // Retornar impuesto mensual
            }
        }

        // TRAMO 2: Más de 5 hasta 20 UIT → 14%
        if ($taxableIncomeInUit > $taxParams->rent_tax_tramo1_uit) {
            $tramo2Limit = $taxParams->rent_tax_tramo2_uit;
            $tramo2Base = $taxParams->rent_tax_tramo1_uit;
            $tramo2Amount = min($taxableIncomeInUit, $tramo2Limit) - $tramo2Base;
            $annualTax += ($tramo2Amount * $taxParams->uit_amount) * ($taxParams->rent_tax_tramo2_rate / 100);
            
            if ($taxableIncomeInUit <= $tramo2Limit) {
                return round($annualTax / 12, 2);
            }
        }

        // TRAMO 3: Más de 20 hasta 35 UIT → 17%
        if ($taxableIncomeInUit > $taxParams->rent_tax_tramo2_uit) {
            $tramo3Limit = $taxParams->rent_tax_tramo3_uit;
            $tramo3Base = $taxParams->rent_tax_tramo2_uit;
            $tramo3Amount = min($taxableIncomeInUit, $tramo3Limit) - $tramo3Base;
            $annualTax += ($tramo3Amount * $taxParams->uit_amount) * ($taxParams->rent_tax_tramo3_rate / 100);
            
            if ($taxableIncomeInUit <= $tramo3Limit) {
                return round($annualTax / 12, 2);
            }
        }

        // TRAMO 4: Más de 35 hasta 45 UIT → 20%
        if ($taxableIncomeInUit > $taxParams->rent_tax_tramo3_uit) {
            $tramo4Limit = $taxParams->rent_tax_tramo4_uit;
            $tramo4Base = $taxParams->rent_tax_tramo3_uit;
            $tramo4Amount = min($taxableIncomeInUit, $tramo4Limit) - $tramo4Base;
            $annualTax += ($tramo4Amount * $taxParams->uit_amount) * ($taxParams->rent_tax_tramo4_rate / 100);
            
            if ($taxableIncomeInUit <= $tramo4Limit) {
                return round($annualTax / 12, 2);
            }
        }

        // TRAMO 5: Más de 45 UIT → 30%
        if ($taxableIncomeInUit > $taxParams->rent_tax_tramo4_uit) {
            $tramo5Base = $taxParams->rent_tax_tramo4_uit;
            $tramo5Amount = $taxableIncomeInUit - $tramo5Base;
            $annualTax += ($tramo5Amount * $taxParams->uit_amount) * ($taxParams->rent_tax_tramo5_rate / 100);
        }

        // Retornar impuesto mensual
        return round($annualTax / 12, 2);
    }

    /**
     * Calcular EsSalud (empleador)
     * 
     * El empleador paga 9% del salario bruto.
     * No se descuenta al empleado, pero se calcula para conocer el costo total.
     * 
     * @param float $grossSalary
     * @param TaxParameter $taxParams
     * @return float
     */
    protected function calculateEmployerEssalud(float $grossSalary, TaxParameter $taxParams): float
    {
        return $grossSalary * ($taxParams->essalud_rate / 100);
    }

    /**
     * Calcular descuento de EsSalud del empleado (9%)
     * 
     * El empleado paga 9% del salario bruto para el Seguro de Salud.
     * Este descuento se realiza mensualmente sobre el salario bruto.
     * 
     * @param float $grossSalary
     * @param TaxParameter $taxParams
     * @return float
     */
    protected function calculateEmployeeEssalud(float $grossSalary, TaxParameter $taxParams): float
    {
        return $grossSalary * ($taxParams->essalud_rate / 100);
    }

    /**
     * Calcular horas extras
     * 
     * @param Employee $employee
     * @param int $month
     * @param int $year
     * @return float
     */
    public function calculateOvertimeAmount(Employee $employee, int $month, int $year): float
    {
        // Obtener horas extras del período desde asistencias
        $overtimeHours = $employee->attendances()
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->sum('overtime_hours');

        if ($overtimeHours <= 0) {
            return 0;
        }

        // Calcular tarifa por hora (base_salary / 160 horas mensuales estándar)
        $hourlyRate = $employee->base_salary / 160;

        // En Perú, las primeras 2 horas extra se pagan 1.25x
        // Las siguientes horas se pagan 1.35x
        // Por simplicidad, usamos 1.25x para todas
        $overtimeRate = 1.25;

        return round($overtimeHours * $hourlyRate * $overtimeRate, 2);
    }

    /**
     * Obtener resumen de cálculo para mostrar al usuario
     * 
     * @param array $calculation Resultado de calculatePayroll
     * @param TaxParameter $taxParams Parámetros usados
     * @return array
     */
    public function getCalculationSummary(array $calculation, TaxParameter $taxParams): array
    {
        return [
            'ingresos' => [
                'salario_base' => $calculation['base_salary'],
                'comisiones' => $calculation['commissions_amount'],
                'bonos' => $calculation['bonuses_amount'],
                'horas_extras' => $calculation['overtime_amount'],
                'asignacion_familiar' => $calculation['family_allowance'],
                'total_bruto' => $calculation['gross_salary'],
            ],
            'descuentos' => [
                'sistema_pensiones' => [
                    'tipo' => $calculation['pension_system'],
                    'proveedor' => $calculation['afp_provider'],
                    'aporte' => $calculation['afp_contribution'] ?: $calculation['onp_contribution'],
                    'comision' => $calculation['afp_commission'],
                    'seguro' => $calculation['afp_insurance'],
                    'total' => $calculation['total_pension'],
                ],
                'impuesto_renta_5ta' => $calculation['rent_tax_5th'],
                'total_descuentos' => $calculation['total_deductions'],
            ],
            'totales' => [
                'salario_bruto' => $calculation['gross_salary'],
                'total_descuentos' => $calculation['total_deductions'],
                'salario_neto' => $calculation['net_salary'],
            ],
            'empleador' => [
                'essalud' => $calculation['employer_essalud'],
                'costo_total' => $calculation['gross_salary'] + $calculation['employer_essalud'],
            ],
            'parametros_usados' => [
                'año' => $taxParams->year,
                'uit' => $taxParams->uit_amount,
                'rmv' => $taxParams->minimum_wage,
                'asignacion_familiar' => $taxParams->family_allowance,
            ],
        ];
    }
}
