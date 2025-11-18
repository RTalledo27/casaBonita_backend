<?php

namespace Modules\HumanResources\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Payroll;
use Modules\HumanResources\Repositories\BonusRepository;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\PayrollRepository;

class PayrollService
{
    public function __construct(
        protected PayrollRepository $payrollRepo,
        protected CommissionRepository $commissionRepo,
        protected BonusRepository $bonusRepo,
        protected PayrollCalculationService $calculationService
    ) {}

    public function generatePayrollForEmployee(int $employeeId, int $month, int $year): Payroll
    {
        $employee = Employee::findOrFail($employeeId);
        $period = sprintf('%04d-%02d', $year, $month);

        // Verificar si ya existe nómina para este período
        $existingPayroll = $this->payrollRepo->getByEmployeeAndPeriod($employeeId, $period);
        if ($existingPayroll) {
            throw new \Exception('Ya existe una nómina para este empleado en el período especificado');
        }

        // Calcular fechas del período
        $payPeriodStart = Carbon::create($year, $month, 1);
        $payPeriodEnd = $payPeriodStart->copy()->endOfMonth();
        $payDate = $payPeriodEnd->copy()->addDays(5); // Pago 5 días después del fin de mes

        // Obtener comisiones del período
        $commissions = $this->commissionRepo->getAll([
            'employee_id' => $employeeId,
            'period_month' => $month,
            'period_year' => $year,
            'payment_status' => 'pendiente'
        ]);

        // Obtener bonos del período
        $bonuses = $this->bonusRepo->getAll([
            'employee_id' => $employeeId,
            'period_month' => $month,
            'period_year' => $year,
            'payment_status' => 'pendiente'
        ]);

        // Calcular totales
        $commissionsAmount = $commissions->sum('commission_amount');
        $bonusesAmount = $bonuses->sum('bonus_amount');
        $overtimeAmount = $this->calculationService->calculateOvertimeAmount($employee, $month, $year);

        // ===== USAR NUEVO SERVICIO DE CÁLCULO =====
        $calculation = $this->calculationService->calculatePayroll(
            employee: $employee,
            baseSalary: $employee->base_salary,
            commissionsAmount: $commissionsAmount,
            bonusesAmount: $bonusesAmount,
            overtimeAmount: $overtimeAmount,
            year: $year
        );

        // Crear nómina con datos calculados
        return $this->payrollRepo->create([
            'employee_id' => $employeeId,
            'payroll_period' => $period,
            'pay_period_start' => $payPeriodStart,
            'pay_period_end' => $payPeriodEnd,
            'pay_date' => $payDate,
            
            // Ingresos
            'base_salary' => $calculation['base_salary'],
            'commissions_amount' => $calculation['commissions_amount'],
            'bonuses_amount' => $calculation['bonuses_amount'],
            'overtime_amount' => $calculation['overtime_amount'],
            'family_allowance' => $calculation['family_allowance'],
            'other_income' => 0,
            'gross_salary' => $calculation['gross_salary'],
            
            // Sistema de Pensiones
            'pension_system' => $calculation['pension_system'],
            'afp_provider' => $calculation['afp_provider'],
            'afp_contribution' => $calculation['afp_contribution'],
            'afp_commission' => $calculation['afp_commission'],
            'afp_insurance' => $calculation['afp_insurance'],
            'onp_contribution' => $calculation['onp_contribution'],
            'total_pension' => $calculation['total_pension'],
            
            // Impuestos
            'rent_tax_5th' => $calculation['rent_tax_5th'],
            
            // Deducciones
            'other_deductions' => 0,
            'total_deductions' => $calculation['total_deductions'],
            
            // Totales
            'net_salary' => $calculation['net_salary'],
            
            // Empleador (informativo)
            'employer_essalud' => $calculation['employer_essalud'],
            
            // Estado
            'status' => 'borrador'
        ]);
    }

    public function generatePayrollForAllEmployees(int $month, int $year): array
    {
        $employees = Employee::active()->get();
        $payrolls = [];

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                $payroll = $this->generatePayrollForEmployee($employee->employee_id, $month, $year);
                $payrolls[] = $payroll;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        return $payrolls;
    }

    /**
     * Generar nóminas para múltiples empleados en batch (UNA SOLA LLAMADA)
     * 
     * @param array $employeeIds Array de IDs de empleados
     * @param int $month Mes del período
     * @param int $year Año del período
     * @param string $payDate Fecha de pago
     * @param bool $includeCommissions Si incluye comisiones
     * @param bool $includeBonuses Si incluye bonificaciones
     * @param bool $includeOvertime Si incluye horas extra
     * @return array ['success' => [...], 'failed' => [...], 'successful_count' => X, 'failed_count' => Y]
     */
    public function generatePayrollBatch(
        array $employeeIds,
        int $month,
        int $year,
        string $payDate,
        bool $includeCommissions = true,
        bool $includeBonuses = true,
        bool $includeOvertime = true
    ): array {
        $successfulPayrolls = [];
        $failedPayrolls = [];

        $period = sprintf('%04d-%02d', $year, $month);
        $payPeriodStart = Carbon::create($year, $month, 1);
        $payPeriodEnd = $payPeriodStart->copy()->endOfMonth();
        $payDateCarbon = Carbon::parse($payDate);

        // Procesar cada empleado
        foreach ($employeeIds as $employeeId) {
            try {
                // Buscar empleado
                $employee = Employee::find($employeeId);
                if (!$employee) {
                    $failedPayrolls[] = [
                        'employee_id' => $employeeId,
                        'error' => 'Empleado no encontrado'
                    ];
                    continue;
                }

                // Verificar si ya existe nómina
                $existingPayroll = $this->payrollRepo->getByEmployeeAndPeriod($employeeId, $period);
                if ($existingPayroll) {
                    $failedPayrolls[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->user->name ?? 'N/A',
                        'error' => 'Ya existe una nómina para este período'
                    ];
                    continue;
                }

                // Obtener comisiones si está habilitado
                $commissionsAmount = 0;
                if ($includeCommissions) {
                    $commissions = $this->commissionRepo->getAll([
                        'employee_id' => $employeeId,
                        'period_month' => $month,
                        'period_year' => $year,
                        'payment_status' => 'pendiente'
                    ]);
                    $commissionsAmount = $commissions->sum('commission_amount');
                }

                // Obtener bonos si está habilitado
                $bonusesAmount = 0;
                if ($includeBonuses) {
                    $bonuses = $this->bonusRepo->getAll([
                        'employee_id' => $employeeId,
                        'period_month' => $month,
                        'period_year' => $year,
                        'payment_status' => 'pendiente'
                    ]);
                    $bonusesAmount = $bonuses->sum('bonus_amount');
                }

                // Calcular horas extra si está habilitado
                $overtimeAmount = 0;
                if ($includeOvertime) {
                    $overtimeAmount = $this->calculationService->calculateOvertimeAmount($employee, $month, $year);
                }

                // Calcular nómina usando el servicio de cálculo
                $calculation = $this->calculationService->calculatePayroll(
                    employee: $employee,
                    baseSalary: $employee->base_salary,
                    commissionsAmount: $commissionsAmount,
                    bonusesAmount: $bonusesAmount,
                    overtimeAmount: $overtimeAmount,
                    year: $year
                );

                // Crear nómina con los campos REALES de la BD
                $payroll = $this->payrollRepo->create([
                    'employee_id' => $employeeId,
                    'payroll_period' => $period,
                    'pay_period_start' => $payPeriodStart,
                    'pay_period_end' => $payPeriodEnd,
                    'pay_date' => $payDateCarbon,
                    
                    // Ingresos
                    'base_salary' => $calculation['base_salary'],
                    'family_allowance' => $calculation['family_allowance'] ?? 0,
                    'commissions_amount' => $calculation['commissions_amount'],
                    'bonuses_amount' => $calculation['bonuses_amount'],
                    'overtime_amount' => $calculation['overtime_amount'],
                    'other_income' => 0,
                    'gross_salary' => $calculation['gross_salary'],
                    
                    // Sistema de Pensiones (campos detallados)
                    'pension_system' => $calculation['pension_system'],
                    'afp_provider' => $calculation['afp_provider'] ?? null,
                    'afp_contribution' => $calculation['afp_contribution'] ?? 0,
                    'afp_commission' => $calculation['afp_commission'] ?? 0,
                    'afp_insurance' => $calculation['afp_insurance'] ?? 0,
                    'onp_contribution' => $calculation['onp_contribution'] ?? 0,
                    'total_pension' => $calculation['total_pension'],
                    
                    // Empleador (informativo)
                    'employer_essalud' => $calculation['employer_essalud'] ?? 0,
                    
                    // Seguros y descuentos
                    'employee_essalud' => $calculation['employee_essalud'] ?? 0,
                    
                    // Impuestos
                    'rent_tax_5th' => $calculation['rent_tax_5th'],
                    'other_deductions' => 0,
                    
                    // Totales
                    'total_deductions' => $calculation['total_deductions'],
                    'net_salary' => $calculation['net_salary'],
                    
                    // Estado y metadata
                    'status' => 'borrador',
                    'currency' => 'PEN',
                    'notes' => 'Generada automáticamente desde selección múltiple'
                ]);

                $successfulPayrolls[] = $payroll;

            } catch (\Exception $e) {
                $failedPayrolls[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->user->name ?? 'N/A',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $successfulPayrolls,
            'failed' => $failedPayrolls,
            'successful_count' => count($successfulPayrolls),
            'failed_count' => count($failedPayrolls)
        ];
    }

    public function approvePayroll(int $payrollId, int $approvedBy): bool
    {
        $payroll = $this->payrollRepo->findById($payrollId);
        if (!$payroll || $payroll->status !== 'procesado') {
            return false;
        }

        DB::beginTransaction();
        try {
            // Actualizar estado de la nómina
            $updated = $this->payrollRepo->update($payrollId, [
                'status' => 'aprobado',
                'approved_by' => $approvedBy,
                'approved_at' => now()
            ]);

            if (!$updated) {
                throw new \Exception('Error al actualizar la nómina');
            }

            // Marcar comisiones como pagadas
            $this->markCommissionsAsPaid($payroll);

            // Marcar bonos como pagados
            $this->markBonusesAsPaid($payroll);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Marcar comisiones del período como pagadas
     */
    protected function markCommissionsAsPaid(Payroll $payroll): void
    {
        [$year, $month] = explode('-', $payroll->payroll_period);

        $commissions = $this->commissionRepo->getAll([
            'employee_id' => $payroll->employee_id,
            'period_month' => (int) $month,
            'period_year' => (int) $year,
            'payment_status' => 'pendiente'
        ]);

        foreach ($commissions as $commission) {
            $this->commissionRepo->update($commission->commission_id, [
                'payment_status' => 'pagado',
                'payment_date' => now()->toDateString()
            ]);
        }
    }

    /**
     * Marcar bonos del período como pagados
     */
    protected function markBonusesAsPaid(Payroll $payroll): void
    {
        [$year, $month] = explode('-', $payroll->payroll_period);

        $bonuses = $this->bonusRepo->getAll([
            'employee_id' => $payroll->employee_id,
            'period_month' => (int) $month,
            'period_year' => (int) $year,
            'payment_status' => 'pendiente'
        ]);

        foreach ($bonuses as $bonus) {
            $this->bonusRepo->update($bonus->bonus_id, [
                'payment_status' => 'pagado',
                'payment_date' => now()->toDateString()
            ]);
        }
    }

    public function processPayroll(int $payrollId, int $processedBy): bool
    {
        $payroll = $this->payrollRepo->findById($payrollId);
        if (!$payroll || $payroll->status !== 'borrador') {
            return false;
        }

        return $this->payrollRepo->update($payrollId, [
            'status' => 'procesado',
            'processed_by' => $processedBy
        ]) !== null;
    }

    public function processBulkPayrolls(string $period, string $status, int $processedBy): array
    {
        $payrolls = $this->payrollRepo->getAll([
            'period' => $period,
            'status' => $status
        ]);

        if ($payrolls->isEmpty()) {
            throw new \Exception('No se encontraron nóminas para procesar en el período especificado');
        }

        $processedPayrolls = [];
        $processedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($payrolls as $payroll) {
                if ($payroll->status === 'borrador' || $payroll->status === 'pendiente') {
                    $updated = $this->payrollRepo->update($payroll->payroll_id, [
                        'status' => 'procesado',
                        'processed_by' => $processedBy,
                        'processed_at' => now()
                    ]);

                    if ($updated) {
                        $processedPayrolls[] = $updated;
                        $processedCount++;
                    }
                }
            }

            DB::commit();

            return [
                'count' => $processedCount,
                'payrolls' => collect($processedPayrolls)
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
