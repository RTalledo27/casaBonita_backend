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
        protected BonusRepository $bonusRepo
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
        $overtimeAmount = $this->calculateOvertimeAmount($employeeId, $month, $year);

        // Calcular salario bruto
        $grossSalary = $employee->base_salary + $commissionsAmount + $bonusesAmount + $overtimeAmount;

        // Calcular descuentos
        $incomeTax = $this->calculateIncomeTax($grossSalary);
        $socialSecurity = $this->calculateSocialSecurity($grossSalary, $employee);
        $healthInsurance = $this->calculateHealthInsurance($grossSalary, $employee);
        $totalDeductions = $incomeTax + $socialSecurity + $healthInsurance;

        // Calcular salario neto
        $netSalary = $grossSalary - $totalDeductions;

        return $this->payrollRepo->create([
            'employee_id' => $employeeId,
            'payroll_period' => $period,
            'pay_period_start' => $payPeriodStart,
            'pay_period_end' => $payPeriodEnd,
            'pay_date' => $payDate,
            'base_salary' => $employee->base_salary,
            'commissions_amount' => $commissionsAmount,
            'bonuses_amount' => $bonusesAmount,
            'overtime_amount' => $overtimeAmount,
            'other_income' => 0,
            'gross_salary' => $grossSalary,
            'income_tax' => $incomeTax,
            'social_security' => $socialSecurity,
            'health_insurance' => $healthInsurance,
            'other_deductions' => 0,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
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

    public function approvePayroll(int $payrollId, int $approvedBy): bool
    {
        $payroll = $this->payrollRepo->findById($payrollId);
        if (!$payroll || $payroll->status !== 'procesado') {
            return false;
        }

        return $this->payrollRepo->update($payrollId, [
            'status' => 'aprobado',
            'approved_by' => $approvedBy,
            'approved_at' => now()
        ]) !== null;
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

    protected function calculateOvertimeAmount(int $employeeId, int $month, int $year): float
    {
        $employee = Employee::find($employeeId);
        $overtimeHours = $employee->attendances()
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->sum('overtime_hours');

        // Calcular tarifa por hora extra (1.25x la tarifa normal)
        $hourlyRate = $employee->base_salary / 160; // Asumiendo 160 horas mensuales
        return $overtimeHours * $hourlyRate * 1.25;
    }

    protected function calculateIncomeTax(float $grossSalary): float
    {
        // Tabla de impuesto a la renta de quinta categoría (Perú 2024)
        if ($grossSalary <= 2083.33) return 0; // Hasta 7 UIT anuales
        if ($grossSalary <= 3333.33) return ($grossSalary - 2083.33) * 0.08;
        if ($grossSalary <= 4166.67) return 100 + ($grossSalary - 3333.33) * 0.14;
        if ($grossSalary <= 5833.33) return 216.67 + ($grossSalary - 4166.67) * 0.17;
        if ($grossSalary <= 12500) return 500 + ($grossSalary - 5833.33) * 0.20;
        return 1833.33 + ($grossSalary - 12500) * 0.30;
    }

    protected function calculateSocialSecurity(float $grossSalary, Employee $employee): float
    {
        // ONP: 13% o AFP: ~10-13% (variable por AFP)
        if ($employee->afp_code) {
            return $grossSalary * 0.10; // Promedio AFP
        }
        return $grossSalary * 0.13; // ONP
    }

    protected function calculateHealthInsurance(float $grossSalary, Employee $employee): float
    {
        // EsSalud: 9% (pagado por empleador, no se descuenta al empleado)
        // EPS: Si tiene seguro privado, puede ser un descuento adicional
        if ($employee->health_insurance && $employee->health_insurance !== 'essalud') {
            return $grossSalary * 0.02; // Asumiendo 2% para EPS
        }
        return 0;
    }
}
