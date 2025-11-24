<?php

namespace Modules\HumanResources\Services;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\Sales\Models\Contract;
use Modules\Lots\Models\LotFinancialTemplate;

class CommissionService
{
    public function __construct(
        protected CommissionRepository $commissionRepo,
        protected EmployeeRepository $employeeRepo,
        protected CommissionEvaluator $commissionEvaluator
    ) {}

    public function processCommissionsForPeriod(int $month, int $year): array
    {
        $commissions = [];
        
        DB::transaction(function () use ($month, $year, &$commissions) {
            // Obtener contratos firmados en el período
            $contracts = Contract::with('advisor')
                               ->whereMonth('sign_date', $month)
                               ->whereYear('sign_date', $year)
                               ->where('status', 'vigente')
                               ->whereNotNull('advisor_id')
                               ->get();

            foreach ($contracts as $contract) {
                // Usar LotFinancialTemplate como fuente única de datos financieros
                try {
                    $commissionData = $this->calculateCommissionFromTemplate($contract);
                } catch (Exception $e) {
                        // Fallback a datos del contrato si no hay template
                    // Usar la misma lógica de tabla de rangos que calculateCommissionFromTemplate
                        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
                        $saleType = ($contract->financing_amount && $contract->financing_amount > 0) ? 'financed' : 'cash';
                        $contractDate = $contract->sign_date ? $contract->sign_date->toDateString() : null;
                        $commissionRatePercent = $this->getCommissionRate($salesCount, $contract->term_months, $saleType, $contractDate);
                    $commissionRate = $commissionRatePercent / 100; // Convertir a decimal para consistencia
                    
                    $baseAmount = $contract->total_price ?? $contract->financing_amount;
                    $commissionData = [
                        'commission_amount' => $baseAmount * $commissionRate,
                        'commission_rate' => $commissionRate,
                        'financing_amount' => $baseAmount,
                        'term_months' => $contract->term_months,
                        'template_id' => null,
                        'template_version' => null,
                        'financial_source' => 'contract_direct'
                    ];
                }
                
                // Verificar si ya existe una comisión COMPLETA (padre + 2 hijas) para este contrato
                $existingParentCommissions = Commission::where('contract_id', $contract->contract_id)
                                                       ->where('period_month', $month)
                                                       ->where('period_year', $year)
                                                       ->where('employee_id', $contract->advisor_id)
                                                       ->whereNull('parent_commission_id')
                                                       ->count();
                                                       
                $existingChildCommissions = Commission::where('contract_id', $contract->contract_id)
                                                      ->where('period_month', $month)
                                                      ->where('period_year', $year)
                                                      ->where('employee_id', $contract->advisor_id)
                                                      ->whereNotNull('parent_commission_id')
                                                      ->count();

                // Procesar si no existe comisión completa (padre + 2 hijas) o si faltan comisiones hijas
                $needsProcessing = ($existingParentCommissions == 0) || ($existingParentCommissions > 0 && $existingChildCommissions < 2);
                
                if ($needsProcessing && $contract->advisor && $contract->financing_amount > 0) {
                    $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
                    
                    // Crear pagos divididos automáticamente (incluye comisión padre)
                    $splitCommissions = $this->createSplitCommissions(
                        $contract,
                        $commissionData['commission_amount'],
                        $commissionData['commission_rate'],
                        $salesCount,
                        $month,
                        $year,
                        $commissionData['financial_source'] ?? null,
                        $commissionData['template_id'] ?? null,
                        now()->toDateString()
                    );
                    
                    // Agregar todas las comisiones (padre + divisiones)
                    $commissions = array_merge($commissions, $splitCommissions);
                }
            }
        });

        return $commissions;
    }

    public function calculateCommission(Contract $contract): float
    {
        if (!$contract->advisor) {
            return 0;
        }

        // Solo calcular comisiones para ventas financiadas
        if (!$contract->financing_amount || $contract->financing_amount <= 0) {
            return 0;
        }

        $baseAmount = $contract->total_price ?? $contract->financing_amount;
        $termMonths = $contract->term_months;
        
        // Contar ventas financiadas del asesor en el mismo período
        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
        
        // Determinar tipo de venta (derivado del contrato)
        $saleType = ($contract->financing_amount && $contract->financing_amount > 0) ? 'financed' : 'cash';

        // Determinar el porcentaje según la tabla de comisiones (pasando la fecha del contrato)
        $contractDate = $contract->sign_date ? $contract->sign_date->toDateString() : null;
        $commissionRate = $this->getCommissionRate($salesCount, $termMonths, $saleType, $contractDate);
        
        return $baseAmount * ($commissionRate / 100);
    }

    /**
     * Obtiene el número de ventas financiadas del asesor en el período
     */
    private function getAdvisorFinancedSalesCount(int $advisorId, $signDate): int
    {
        $month = date('n', strtotime($signDate));
        $year = date('Y', strtotime($signDate));
        
        return Contract::where('advisor_id', $advisorId)
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('status', 'vigente')
            ->whereNotNull('financing_amount')
            ->where('financing_amount', '>', 0)
            ->count();
    }

    /**
     * Calcula la comisión basada en LotFinancialTemplate como fuente única
     */
    private function calculateCommissionFromTemplate(Contract $contract): array
    {
        // Obtener el lote y su template financiero
        $lot = $contract->getLot();
        if (!$lot || !$lot->lotFinancialTemplate) {
            throw new Exception('No se encontró template financiero para el lote del contrato');
        }

        $template = $lot->lotFinancialTemplate;
        
        // Usar datos del template como fuente única de verdad
        // 🔥 CAMBIO: Usar precio_venta (precio total) como base para la comisión, no solo el financiado
        $baseAmount = $template->precio_venta ?? $template->financing_amount;
        $termMonths = $template->term_months;
        
        // Contar ventas financiadas del asesor para determinar la tasa correcta
        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
        $saleType = ($contract->financing_amount && $contract->financing_amount > 0) ? 'financed' : 'cash';
        $contractDate = $contract->sign_date ? $contract->sign_date->toDateString() : null;

        // Usar la tabla de rangos por número de ventas (pasando la fecha del contrato)
        $commissionRatePercent = $this->getCommissionRate($salesCount, $termMonths, $saleType, $contractDate);
        $commissionRateDecimal = $commissionRatePercent / 100; // Convertir a decimal para cálculo
        $commissionAmount = $baseAmount * $commissionRateDecimal;
        
        return [
            'commission_amount' => $commissionAmount,
            'commission_rate' => $commissionRatePercent, // Guardar como porcentaje
            'financing_amount' => $baseAmount, // Usamos el monto base (total) para referencia
            'term_months' => $termMonths,
            'sales_count' => $salesCount,
            'template_id' => $template->id,
            'template_version' => $template->version ?? 1,
            'financial_source' => 'lot_financial_template'
        ];
    }

    /**
      * Determina el porcentaje de comisión según la tabla de rangos
      * 
      * @param int $salesCount Número de ventas del asesor en el período
      * @param int $termMonths Plazo en meses del financiamiento
      * @param string|null $saleType Tipo de venta: 'cash', 'financed', o null
      * @param string|null $asOfDate Fecha del contrato (YYYY-MM-DD) para selección temporal de esquemas
      * @return float Porcentaje de comisión
      */
    private function getCommissionRate(int $salesCount, int $termMonths, ?string $saleType = null, ?string $asOfDate = null): float
    {
        // Intentar usar el evaluador dinámico si está disponible
        try {
            $evaluated = $this->commissionEvaluator->evaluate($salesCount, $termMonths, $asOfDate, $saleType);
            if (is_array($evaluated) && isset($evaluated['percentage'])) {
                return (float)$evaluated['percentage'];
            }
        } catch (\Throwable $e) {
            // No interrumpir: caemos al fallback hardcoded
        }

        // Fallback: Determinar si es plazo corto (12/24/36) o largo (48/60)
        $isShortTerm = in_array($termMonths, [12, 24, 36]);

        if ($salesCount >= 10) {
            return $isShortTerm ? 4.20 : 3.00;
        } elseif ($salesCount >= 8) {
            return $isShortTerm ? 4.00 : 2.50;
        } elseif ($salesCount >= 6) {
            return $isShortTerm ? 3.00 : 1.50;
        } else {
            return $isShortTerm ? 2.00 : 1.00;
        }
    }

    /**
     * Calcula la comisión basada en el monto de financiamiento y plazo
     */
    private function calculateCommissionRate(float $financingAmount, int $termMonths): float
    {
        // Lógica de comisiones basada en el plazo
        if ($termMonths <= 12) {
            return 0.03; // 3%
        } elseif ($termMonths <= 24) {
            return 0.035; // 3.5%
        } elseif ($termMonths <= 36) {
            return 0.04; // 4%
        } else {
            return 0.045; // 4.5%
        }
    }

    /**
     * Crea comisiones divididas según el número de ventas
     */
    private function createSplitCommissions(
        Contract $contract,
        float $totalAmount,
        float $commissionRate,
        int $salesCount,
        int $month,
        int $year,
        string $financialSource = null,
        int $templateVersionId = null,
        string $calculationDate = null
    ): array {
        $commissions = [];
        $schemeId = null;
        $ruleId = null;
        try {
            $saleType = ($contract->financing_amount && $contract->financing_amount > 0) ? 'financed' : 'cash';
            $evaluated = $this->commissionEvaluator->evaluate($salesCount, $contract->term_months, $calculationDate, $saleType);
            if (is_array($evaluated)) {
                $schemeId = $evaluated['scheme_id'] ?? null;
                $ruleId = $evaluated['rule_id'] ?? null;
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        
        // Validación adicional: verificar que no exista una comisión PADRE para este contrato/empleado/período
        $existingParentCount = Commission::where('contract_id', $contract->contract_id)
                                         ->where('employee_id', $contract->advisor_id)
                                         ->where('period_month', $month)
                                         ->where('period_year', $year)
                                         ->whereNull('parent_commission_id') // Solo comisiones padre
                                         ->count();
        
        if ($existingParentCount > 0) {
            // Ya existe una comisión padre, verificar si faltan las comisiones hijas
            $existingChildrenCount = Commission::where('contract_id', $contract->contract_id)
                                               ->where('employee_id', $contract->advisor_id)
                                               ->where('period_month', $month)
                                               ->where('period_year', $year)
                                               ->whereNotNull('parent_commission_id') // Solo comisiones hijas
                                               ->count();
            
            if ($existingChildrenCount >= 2) {
                // Ya existen las comisiones hijas, retornar array vacío
                return [];
            }
            
            // Obtener la comisión padre existente para crear las hijas faltantes
            $parentCommission = Commission::where('contract_id', $contract->contract_id)
                                          ->where('employee_id', $contract->advisor_id)
                                          ->where('period_month', $month)
                                          ->where('period_year', $year)
                                          ->whereNull('parent_commission_id')
                                          ->first();
        } else {
            // No existe comisión padre, crear una nueva
            $parentCommission = null;
        }
        
        // Generar período de comisión (YYYY-MM)
        $commissionPeriod = Commission::generateCommissionPeriod($month, $year);
        
        // Determinar porcentajes de división según número de ventas
        if ($salesCount >= 10) {
            // Más de 10 ventas: 70% primer mes, 30% segundo mes
            $firstPaymentPercentage = 70;
            $secondPaymentPercentage = 30;
        } else {
            // 1-10 ventas: 50% primer mes, 50% segundo mes
            $firstPaymentPercentage = 50;
            $secondPaymentPercentage = 50;
        }
        
        // Crear comisión principal (padre) solo si no existe - NO PAGABLE (registro de control)
        if ($parentCommission === null) {
            $parentCommission = $this->commissionRepo->create([
                'employee_id' => $contract->advisor_id,
                'contract_id' => $contract->contract_id,
                'commission_type' => 'venta_financiada',
                'sale_amount' => $contract->total_price,  // 🔥 PRECIO FINAL pagado por el cliente (después de descuento)
                'installment_plan' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => $totalAmount,
                'period_month' => $month,
                'period_year' => $year,
                'commission_period' => $commissionPeriod,
                'payment_period' => null, // Se asignará cuando se procese el pago
                'payment_percentage' => 100.0,
                'status' => 'generated',
                'payment_status' => 'pendiente',
                'parent_commission_id' => null,
                'payment_part' => 1,
                'total_commission_amount' => $totalAmount,
                'sales_count' => $salesCount,
                'is_payable' => false, // Registro de control, no pagable directamente
                'financial_source' => $financialSource,
                'template_version_id' => $templateVersionId,
                'calculation_date' => $calculationDate,
                'commission_scheme_id' => $schemeId,
                'commission_rule_id' => $ruleId,
                'applied_at' => $calculationDate,
                'notes' => "Comisión por venta financiada - {$salesCount} ventas - Total: $" . number_format($totalAmount, 2)
            ]);
            
            $commissions[] = $parentCommission;
        }
        
        // Si hay división de pagos, crear los registros de pago dividido
        if ($secondPaymentPercentage > 0) {
            // Primer pago (mes siguiente al de generación)
            $paymentMonth = $month + 1;
            $paymentYear = $year;
            
            // Ajustar año si es diciembre
            if ($paymentMonth > 12) {
                $paymentMonth = 1;
                $paymentYear++;
            }
            
            $firstPaymentPeriod = Commission::generatePaymentPeriod($paymentMonth, $paymentYear, 1);
            $firstAmount = ($totalAmount * $firstPaymentPercentage) / 100;
            
            $firstPayment = $this->commissionRepo->create([
                'employee_id' => $contract->advisor_id,
                'contract_id' => $contract->contract_id,
                'commission_type' => 'venta_financiada',
                'sale_amount' => $contract->total_price,  // 🔥 PRECIO FINAL pagado por el cliente (después de descuento)
                'installment_plan' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => round($firstAmount, 2),
                'period_month' => $month,
                'period_year' => $year,
                'commission_period' => $commissionPeriod,
                'payment_period' => $firstPaymentPeriod,
                'payment_percentage' => $firstPaymentPercentage,
                'status' => 'generated',
                'payment_status' => 'pendiente',
                'parent_commission_id' => $parentCommission->commission_id,
                'payment_part' => 1,
                'total_commission_amount' => $totalAmount,
                'sales_count' => $salesCount,
                'is_payable' => true, // División pagable
                'financial_source' => $financialSource,
                'template_version_id' => $templateVersionId,
                'calculation_date' => $calculationDate,
                'commission_scheme_id' => $schemeId,
                'commission_rule_id' => $ruleId,
                'applied_at' => $calculationDate,
                'notes' => "Pago dividido 1/2 - {$firstPaymentPercentage}% - Período: {$firstPaymentPeriod}"
            ]);
            
            $commissions[] = $firstPayment;
            
            // Segundo pago (dos meses después de la generación)
            $secondPaymentMonth = $month + 2;
            $secondPaymentYear = $year;
            
            // Ajustar año si es necesario
            if ($secondPaymentMonth > 12) {
                $secondPaymentMonth -= 12;
                $secondPaymentYear++;
            }
            
            $secondPaymentPeriod = Commission::generatePaymentPeriod($secondPaymentMonth, $secondPaymentYear, 2);
            $secondAmount = ($totalAmount * $secondPaymentPercentage) / 100;
            
            $secondPayment = $this->commissionRepo->create([
                'employee_id' => $contract->advisor_id,
                'contract_id' => $contract->contract_id,
                'commission_type' => 'venta_financiada',
                'sale_amount' => $contract->total_price,  // 🔥 PRECIO FINAL pagado por el cliente (después de descuento)
                'installment_plan' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => round($secondAmount, 2),
                'period_month' => $month,
                'period_year' => $year,
                'commission_period' => $commissionPeriod,
                'payment_period' => $secondPaymentPeriod,
                'payment_percentage' => $secondPaymentPercentage,
                'status' => 'generated',
                'payment_status' => 'pendiente',
                'parent_commission_id' => $parentCommission->commission_id,
                'payment_part' => 2,
                'total_commission_amount' => $totalAmount,
                'sales_count' => $salesCount,
                'is_payable' => true, // División pagable
                'financial_source' => $financialSource,
                'template_version_id' => $templateVersionId,
                'calculation_date' => $calculationDate,
                'commission_scheme_id' => $schemeId,
                'commission_rule_id' => $ruleId,
                'applied_at' => $calculationDate,
                'notes' => "Pago dividido 2/2 - {$secondPaymentPercentage}% - Período: {$secondPaymentPeriod}"
            ]);
            
            $commissions[] = $secondPayment;
        }
        
        return $commissions;
    }

    public function payCommissions(array $commissionIds): bool
    {
        return DB::transaction(function () use ($commissionIds) {
            $updated = $this->commissionRepo->markMultipleAsPaid($commissionIds);
            return $updated > 0;
        });
    }

    public function markMultipleAsPaid(array $commissionIds): array
    {
        try {
            $updatedCount = $this->commissionRepo->markMultipleAsPaid($commissionIds);
            
            return [
                'success' => true,
                'message' => "Se marcaron {$updatedCount} comisiones como pagadas",
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al marcar comisiones como pagadas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crea un pago dividido para una comisión
     */
    public function createSplitPayment(int $commissionId, array $splitData): array
    {
        try {
            // Validar que la comisión existe
            $commission = $this->commissionRepo->findById($commissionId);
            if (!$commission) {
                return [
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ];
            }

            // Validar que no esté completamente pagada
            if ($commission->status === 'fully_paid') {
                return [
                    'success' => false,
                    'message' => 'La comisión ya está completamente pagada'
                ];
            }

            // Validar porcentajes
            $totalPaid = $commission->childCommissions()->sum('payment_percentage');
            $newTotal = $totalPaid + $splitData['percentage'];
            
            if ($newTotal > 100) {
                return [
                    'success' => false,
                    'message' => "El porcentaje excede el límite. Ya pagado: {$totalPaid}%, intentando agregar: {$splitData['percentage']}%"
                ];
            }

            // Determinar el número de parte del pago
            $paymentPart = $commission->childCommissions()->max('payment_part') + 1;

            $splitPayment = $this->commissionRepo->processSplitPayment(
                $commissionId,
                $splitData['percentage'],
                $splitData['payment_period'],
                $paymentPart
            );

            return [
                'success' => true,
                'message' => 'Pago dividido creado exitosamente',
                'split_payment' => $splitPayment,
                'summary' => $this->commissionRepo->getSplitPaymentSummary($commissionId)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear pago dividido: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene comisiones por período de generación
     */
    public function getCommissionsByPeriod(string $period): array
    {
        try {
            $commissions = $this->commissionRepo->getByCommissionPeriod($period);
            
            return [
                'success' => true,
                'commissions' => $commissions,
                'total_amount' => $commissions->sum('commission_amount'),
                'count' => $commissions->count()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener comisiones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene comisiones pendientes para un período
     */
    public function getPendingCommissions(string $period): array
    {
        try {
            $commissions = $this->commissionRepo->getPendingForCommissionPeriod($period);
            
            return [
                'success' => true,
                'commissions' => $commissions,
                'total_amount' => $commissions->sum('commission_amount'),
                'count' => $commissions->count()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener comisiones pendientes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Procesa comisiones para incluir en nómina
     */
    public function processCommissionsForPayroll(string $commissionPeriod, string $paymentPeriod, array $commissionIds = []): array
    {
        try {
            $query = $this->commissionRepo->getPendingForCommissionPeriod($commissionPeriod);
            
            if (!empty($commissionIds)) {
                $commissions = $query->whereIn('commission_id', $commissionIds);
            } else {
                $commissions = $query;
            }

            $processedCount = 0;
            $totalAmount = 0;

            foreach ($commissions as $commission) {
                // Actualizar período de pago y estado
                $commission->update([
                    'payment_period' => $paymentPeriod,
                    'status' => 'fully_paid',
                    'payment_status' => 'pagado',
                    'payment_date' => now()->toDateString(),
                    'payment_percentage' => 100.0
                ]);
                
                $processedCount++;
                $totalAmount += $commission->commission_amount;
            }

            return [
                'success' => true,
                'message' => "Se procesaron {$processedCount} comisiones para nómina",
                'processed_count' => $processedCount,
                'total_amount' => $totalAmount,
                'commission_period' => $commissionPeriod,
                'payment_period' => $paymentPeriod
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al procesar comisiones para nómina: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el resumen de pagos divididos
     */
    public function getSplitPaymentSummary(int $commissionId): array
    {
        try {
            $summary = $this->commissionRepo->getSplitPaymentSummary($commissionId);
            
            if (empty($summary)) {
                return [
                    'success' => false,
                    'message' => 'Comisión no encontrada'
                ];
            }

            return [
                'success' => true,
                'summary' => $summary
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener resumen de pagos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el detalle de ventas individuales con sus comisiones para un asesor
     */
    public function getAdvisorSalesDetail(int $employeeId, int $month, int $year): array
    {
        // Obtener empleado para verificar elegibilidad
        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee || !$employee->is_commission_eligible) {
            throw new \Exception('Empleado no encontrado o no es elegible para comisiones');
        }

        // Obtener todos los contratos del asesor en el período
        $contracts = Contract::with(['reservation.lot.manzana', 'reservation.client'])
            ->where('advisor_id', $employeeId)
            ->whereMonth('sign_date', $month)
            ->whereYear('sign_date', $year)
            ->where('financing_amount', '>', 0)
            ->orderBy('sign_date')
            ->get();

        $salesDetail = [];
        $totalCommissions = 0;
        $salesCount = $contracts->count();
        $firstMonthTotal = 0;
        $secondMonthTotal = 0;
        
        // Determinar tipo de división según cantidad de ventas
        $splitType = $salesCount > 10 ? '70/30' : '50/50';
        $firstPercentage = $salesCount > 10 ? 70 : 50;
        $secondPercentage = $salesCount > 10 ? 30 : 50;

        foreach ($contracts as $index => $contract) {
            // Obtener las comisiones reales creadas para este contrato
            $commissions = Commission::where('contract_id', $contract->contract_id)
                ->where('employee_id', $employeeId)
                ->get();
            
            // Usar datos reales de comisiones en lugar de calcular
            $commissionAmount = $commissions->sum('commission_amount');
            $commissionRate = $commissions->isNotEmpty() ? 
                ($commissionAmount / $contract->financing_amount) * 100 : 0;
            
            // Calcular pagos basado en comisiones padre e hijas
            $parentCommissions = $commissions->whereNull('parent_commission_id');
            $childCommissions = $commissions->whereNotNull('parent_commission_id');
            
            // Obtener pagos divididos (comisiones hijas)
            $firstPayment = $childCommissions->where('payment_part', 1)->sum('commission_amount');
            $secondPayment = $childCommissions->where('payment_part', 2)->sum('commission_amount');
            
            // Si no hay comisiones hijas, usar la comisión padre completa como primer pago
            if ($firstPayment == 0 && $secondPayment == 0 && $parentCommissions->isNotEmpty()) {
                $firstPayment = $parentCommissions->sum('commission_amount');
            }
            
            $firstMonthTotal += $firstPayment;
            $secondMonthTotal += $secondPayment;

            $saleDetail = [
                'contract_number' => $contract->contract_number,
                'client_name' => $contract->reservation->client->full_name ?? 'N/A',
                'financing_amount' => $contract->financing_amount,
                'term_months' => $contract->term_months,
                'commission_percentage' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'first_payment' => $firstPayment,
                'second_payment' => $secondPayment,
                'payment_split_type' => $splitType,
                'project_name' => $contract->reservation->lot->manzana->name ?? 'N/A',
                'lot_number' => $contract->reservation->lot->num_lot ?? 'N/A',
                'sign_date' => $contract->sign_date->format('Y-m-d'),
                'commissions' => []
            ];

            // Agregar detalles de las comisiones (padre e hijas)
            foreach ($commissions as $commission) {
                $saleDetail['commissions'][] = [
                    'commission_id' => $commission->commission_id,
                    'is_parent' => is_null($commission->parent_commission_id),
                    'payment_part' => $commission->payment_part,
                    'commission_amount' => $commission->commission_amount,
                    'payment_status' => $commission->payment_status,
                    'payment_date' => $commission->payment_date?->format('Y-m-d'),
                    'period_month' => $commission->period_month,
                    'period_year' => $commission->period_year,
                    'payment_period' => $commission->payment_period
                ];
            }

            $salesDetail[] = $saleDetail;
            $totalCommissions += $commissionAmount;
        }

        return [
            'success' => true,
            'data' => [
                'summary' => [
                    'total_sales' => $salesCount,
                    'total_commission' => $totalCommissions,
                    'average_percentage' => $salesCount > 0 ? ($totalCommissions / $contracts->sum('financing_amount')) * 100 : 0,
                    'first_month_total' => $firstMonthTotal,
                    'second_month_total' => $secondMonthTotal,
                    'split_type' => $splitType
                ],
                'sales' => $salesDetail,
                'employee' => $employee,
                'period' => [
                    'month' => $month,
                    'year' => $year
                ]
            ],
            'message' => 'Detalle de ventas obtenido exitosamente'
        ];
    }

    public function getTopPerformers(int $month, int $year, int $limit = 10): Collection
    {
        return $this->employeeRepo->getTopPerformers($month, $year, $limit);
    }

    public function getAdvisorDashboard(int $employeeId, int $month, int $year): array
    {
        $employee = $this->employeeRepo->findById($employeeId);
        
        if (!$employee || !$employee->is_commission_eligible) {
            throw new \Exception('Empleado no encontrado o no es elegible para comisiones');
        }

        // Obtener contratos del mes con las relaciones necesarias
        $monthlySales = Contract::with(['reservation.client'])
            ->whereHas('reservation', function ($query) use ($employeeId, $month, $year) {
                 $query->where('advisor_id', $employeeId)
                     ->whereMonth('reservation_date', $month)
                     ->whereYear('reservation_date', $year);
             })
            ->where('status', 'vigente')
            ->get();

        $monthlyCommissions = $employee->calculateMonthlyCommissions($month, $year);
        $monthlyBonuses = $employee->calculateMonthlyBonuses($month, $year);
        
        $topPerformers = $this->getTopPerformers($month, $year);
        $ranking = $topPerformers->search(function ($performer) use ($employeeId) {
            return $performer->employee_id === $employeeId;
        });

        // Calcular meta dinámica basada en el historial del asesor (cantidad de contratos)
        $dynamicGoal = $this->calculateDynamicGoal($employeeId, $month, $year);
        $totalSales = $monthlySales->sum('total_price');
        $salesCount = $monthlySales->count();
        
        // El ranking solo aplica para asesores de ventas
        $isEligibleForRanking = $employee->isSalesAdvisor();
        
        return [
            'employee' => $employee,
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $this->getMonthLabel($month) . ' ' . $year
            ],
            'sales_summary' => [
                'count' => $salesCount,
                'total_amount' => $totalSales,
                'goal' => $dynamicGoal, // Meta en cantidad de contratos
                'achievement_percentage' => $dynamicGoal > 0 
                    ? round(($salesCount / $dynamicGoal) * 100, 2)
                    : 0
            ],
            'earnings_summary' => [
                'base_salary' => (float)$employee->base_salary,
                'commissions' => (float)$monthlyCommissions,
                'bonuses' => (float)$monthlyBonuses,
                'total_estimated' => $employee->base_salary +
                    $monthlyCommissions +
                    $monthlyBonuses
            ],
            'performance' => [
                'ranking' => $isEligibleForRanking && $ranking !== false ? $ranking + 1 : null,
                'total_advisors' => $topPerformers->count(),
                'is_eligible_for_ranking' => $isEligibleForRanking
            ],
            'recent_contracts' => $monthlySales->take(5)->map(function ($contract) {
                $clientName = 'Cliente no disponible';
                if ($contract->reservation && $contract->reservation->client) {
                    $client = $contract->reservation->client;
                    $clientName = ($client->first_name ?? '') . ' ' . ($client->last_name ?? '');
                    $clientName = trim($clientName) ?: 'Cliente sin nombre';
                }
                
                return [
                    'contract_number' => $contract->contract_number,
                    'total_price' => $contract->total_price,
                    'sign_date' => $contract->sign_date->format('Y-m-d'),
                    'client_name' => $clientName
                ];
            })
        ];
    }

    public function getAdminDashboard(int $month, int $year): array
    {
        // Obtener todas las comisiones del período
        $allCommissions = $this->commissionRepo->getAll([
            'period_month' => $month,
            'period_year' => $year
        ]);

        // Calcular totales por estado
        $totalAmount = $allCommissions->sum('commission_amount');
        $totalCount = $allCommissions->count();
        
        // Agrupar por estado y calcular montos
        $byStatus = $allCommissions->groupBy('payment_status');
        
        $paidAmount = $byStatus->get('pagado', collect())->sum('commission_amount');
        $pendingAmount = $byStatus->get('pendiente', collect())->sum('commission_amount');
        $processingAmount = $byStatus->get('procesando', collect())->sum('commission_amount');
        
        $paidCount = $byStatus->get('pagado', collect())->count();
        $pendingCount = $byStatus->get('pendiente', collect())->count();
        $processingCount = $byStatus->get('procesando', collect())->count();

        $topPerformers = $this->getTopPerformers($month, $year);

        $bonusService = app(\Modules\HumanResources\Services\BonusService::class);
        $bonusSummary = $bonusService->getBonusSummary($month, $year);

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $this->getMonthLabel($month) . ' ' . $year
            ],
            'commissions_summary' => [
                'total_amount' => $totalAmount,
                'count' => $totalCount,
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'processing' => $processingCount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'processing_amount' => $processingAmount,
                'by_status' => [
                    'pagado' => [
                        'count' => $paidCount,
                        'total_amount' => $paidAmount
                    ],
                    'pendiente' => [
                        'count' => $pendingCount,
                        'total_amount' => $pendingAmount
                    ],
                    'procesando' => [
                        'count' => $processingCount,
                        'total_amount' => $processingAmount
                    ]
                ]
            ],
            'bonuses_summary' => $bonusSummary,
            'top_performers' => $topPerformers
        ];
    }


    /**
     * Procesa el pago de una comisión específica
     */
    public function processCommissionPayment(Commission $commission, int $paymentPart): array
    {
        try {
            // Validar que la comisión no esté ya pagada
            if ($commission->payment_status === 'pagado') {
                return [
                    'success' => false,
                    'message' => 'La comisión ya está marcada como pagada'
                ];
            }

            // Validar que la comisión sea elegible para pago
            if (!$commission->is_eligible_for_payment) {
                return [
                    'success' => false,
                    'message' => 'La comisión no es elegible para pago'
                ];
            }

            // Verificar si es una comisión hija (parte de un pago dividido)
            if ($commission->parent_commission_id) {
                // Es una comisión hija - actualizar solo esta comisión
                $commission->update([
                    'payment_status' => 'pagado',
                    'payment_date' => now()->toDateString(),
                    'status' => 'fully_paid'
                ]);

                // Actualizar el estado de la comisión padre basado en el estado de todas las hijas
                $this->updateParentCommissionStatus($commission->parent_commission_id);

                return [
                    'success' => true,
                    'message' => 'Parte de la comisión procesada exitosamente',
                    'data' => [
                        'commission_id' => $commission->commission_id,
                        'payment_part' => $paymentPart,
                        'amount' => $commission->commission_amount,
                        'payment_date' => $commission->payment_date,
                        'is_child_commission' => true
                    ]
                ];
            } else {
                // Es una comisión padre (sin divisiones) - actualizar normalmente
                $commission->update([
                    'payment_status' => 'pagado',
                    'payment_date' => now()->toDateString(),
                    'status' => 'fully_paid'
                ]);

                return [
                    'success' => true,
                    'message' => 'Comisión procesada exitosamente',
                    'data' => [
                        'commission_id' => $commission->commission_id,
                        'payment_part' => $paymentPart,
                        'amount' => $commission->commission_amount,
                        'payment_date' => $commission->payment_date,
                        'is_child_commission' => false
                    ]
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al procesar el pago de la comisión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza el estado de la comisión padre basado en el estado de sus comisiones hijas
     */
    private function updateParentCommissionStatus(int $parentCommissionId): void
    {
        try {
            $parentCommission = Commission::find($parentCommissionId);
            if (!$parentCommission) {
                return;
            }

            // Obtener todas las comisiones hijas
            $childCommissions = Commission::where('parent_commission_id', $parentCommissionId)->get();
            
            if ($childCommissions->isEmpty()) {
                return;
            }

            // Contar cuántas están pagadas
            $totalChildren = $childCommissions->count();
            $paidChildren = $childCommissions->where('payment_status', 'pagado')->count();

            // Determinar el nuevo estado de la comisión padre
            if ($paidChildren === 0) {
                // Ninguna hija pagada - mantener estado actual
                $newStatus = 'generated';
                $paymentStatus = 'pendiente';
            } elseif ($paidChildren === $totalChildren) {
                // Todas las hijas pagadas - marcar como completamente pagada
                $newStatus = 'fully_paid';
                $paymentStatus = 'pagado';
            } else {
                // Algunas hijas pagadas - marcar como parcialmente pagada
                $newStatus = 'partially_paid';
                $paymentStatus = 'parcial';
            }

            // Actualizar la comisión padre
            $updateData = [
                'status' => $newStatus,
                'payment_status' => $paymentStatus,
                'updated_at' => now()
            ];

            // Si todas están pagadas, establecer fecha de pago
            if ($paidChildren === $totalChildren) {
                $updateData['payment_date'] = now()->toDateString();
            }

            $parentCommission->update($updateData);

        } catch (Exception $e) {
            // Log del error pero no interrumpir el flujo principal
            Log::error('Error al actualizar estado de comisión padre', [
                'parent_commission_id' => $parentCommissionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getMonthLabel(int $month): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $months[$month] ?? 'Mes desconocido';
    }

    /**
     * Calcula una meta dinámica para el asesor basada en su historial de ventas
     * 
     * IMPORTANTE: La meta es en CANTIDAD DE CONTRATOS, no en monto total
     * 
     * Estrategia:
     * 1. Si tiene individual_goal definido, usa ese valor (cantidad de contratos)
     * 2. Si no, calcula el promedio de CANTIDAD de contratos de los últimos 3 meses
     * 3. Si es nuevo (sin historial), usa una meta base de 10 contratos
     */
    private function calculateDynamicGoal(int $employeeId, int $currentMonth, int $currentYear): float
    {
        $employee = $this->employeeRepo->findById($employeeId);
        
        // 1. Si el empleado tiene una meta individual definida, usarla
        if ($employee && $employee->individual_goal > 0) {
            return (float) $employee->individual_goal;
        }

        // 2. Calcular promedio de CANTIDAD de contratos de los últimos 3 meses (excluyendo el mes actual)
        $historicalContractsCount = Contract::whereHas('reservation', function ($query) use ($employeeId, $currentMonth, $currentYear) {
                $query->where('advisor_id', $employeeId)
                    ->where(function ($q) use ($currentMonth, $currentYear) {
                        // Últimos 3 meses antes del mes actual
                        $q->where(function ($sq) use ($currentMonth, $currentYear) {
                            // Mes anterior
                            $prevMonth = $currentMonth - 1;
                            $prevYear = $currentYear;
                            if ($prevMonth <= 0) {
                                $prevMonth = 12;
                                $prevYear--;
                            }
                            $sq->whereMonth('reservation_date', $prevMonth)
                               ->whereYear('reservation_date', $prevYear);
                        })
                        ->orWhere(function ($sq) use ($currentMonth, $currentYear) {
                            // Hace 2 meses
                            $prev2Month = $currentMonth - 2;
                            $prev2Year = $currentYear;
                            if ($prev2Month <= 0) {
                                $prev2Month = 12 + $prev2Month;
                                $prev2Year--;
                            }
                            $sq->whereMonth('reservation_date', $prev2Month)
                               ->whereYear('reservation_date', $prev2Year);
                        })
                        ->orWhere(function ($sq) use ($currentMonth, $currentYear) {
                            // Hace 3 meses
                            $prev3Month = $currentMonth - 3;
                            $prev3Year = $currentYear;
                            if ($prev3Month <= 0) {
                                $prev3Month = 12 + $prev3Month;
                                $prev3Year--;
                            }
                            $sq->whereMonth('reservation_date', $prev3Month)
                               ->whereYear('reservation_date', $prev3Year);
                        });
                    });
            })
            ->where('status', 'vigente')
            ->count(); // Contamos contratos, no sumamos montos

        // Si tiene historial, promedio + 10% de incremento como meta
        if ($historicalContractsCount > 0) {
            $averageMonthlyContracts = $historicalContractsCount / 3;
            // Meta = promedio + 10% de incremento para motivar crecimiento
            return round($averageMonthlyContracts * 1.10, 0); // Redondeamos a entero (contratos completos)
        }

        // 3. Si es nuevo (sin historial), meta base de 10 contratos para asesores nuevos
        return 10;
    }

}