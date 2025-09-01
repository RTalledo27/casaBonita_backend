<?php

namespace Modules\HumanResources\Services;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
        protected EmployeeRepository $employeeRepo
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
                    $commissionRatePercent = $this->getCommissionRate($salesCount, $contract->term_months);
                    $commissionRate = $commissionRatePercent / 100; // Convertir a decimal para consistencia
                    
                    $commissionData = [
                        'commission_amount' => $contract->financing_amount * $commissionRate,
                        'commission_rate' => $commissionRate,
                        'financing_amount' => $contract->financing_amount,
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

        $baseAmount = $contract->financing_amount;
        $termMonths = $contract->term_months;
        
        // Contar ventas financiadas del asesor en el mismo período
        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
        
        // Determinar el porcentaje según la tabla de comisiones
        $commissionRate = $this->getCommissionRate($salesCount, $termMonths);
        
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
        $financingAmount = $template->financing_amount;
        $termMonths = $template->term_months;
        
        // Contar ventas financiadas del asesor para determinar la tasa correcta
        $salesCount = $this->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
        
        // Usar la tabla de rangos por número de ventas (NO el método de porcentajes fijos por plazo)
        $commissionRatePercent = $this->getCommissionRate($salesCount, $termMonths); // Mantener como porcentaje
        $commissionRateDecimal = $commissionRatePercent / 100; // Convertir a decimal para cálculo
        $commissionAmount = $financingAmount * $commissionRateDecimal;
        
        return [
            'commission_amount' => $commissionAmount,
            'commission_rate' => $commissionRatePercent, // Guardar como porcentaje
            'financing_amount' => $financingAmount,
            'term_months' => $termMonths,
            'sales_count' => $salesCount,
            'template_id' => $template->id,
            'template_version' => $template->version ?? 1,
            'financial_source' => 'lot_financial_template'
        ];
    }

    /**
      * Determina el porcentaje de comisión según la tabla de rangos
      */
    private function getCommissionRate(int $salesCount, int $termMonths): float
    {
        // Determinar si es plazo corto (12/24/36) o largo (48/60)
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
                'sale_amount' => $contract->financing_amount,
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
                'sale_amount' => $contract->financing_amount,
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
                'sale_amount' => $contract->financing_amount,
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

        return [
            'employee' => $employee,
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $this->getMonthLabel($month) . ' ' . $year
            ],
            'sales_summary' => [
                'count' => $monthlySales->count(),
                'total_amount' => $monthlySales->sum('total_price'),
                'goal' => $employee->individual_goal ?? 0,
                'achievement_percentage' => $employee->individual_goal > 0 
                    ? round(($monthlySales->sum('total_price') / $employee->individual_goal) * 100, 2)
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
                'ranking' => $ranking !== false ? $ranking + 1 : null,
                'total_advisors' => $topPerformers->count()
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


    private function getMonthLabel(int $month): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $months[$month] ?? 'Mes desconocido';
    }

}