<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\LotFinancialTemplate;
use Modules\HumanResources\Services\CommissionService;
use Carbon\Carbon;

class DebugDanielaCommissions extends Command
{
    protected $signature = 'debug:daniela-commissions';
    protected $description = 'Debug DANIELA commission calculations';

    public function handle()
    {
        // Buscar empleado DANIELA AIRAM MERINO VALIENTE (ID: 7)
        $employee = Employee::where('employee_id', 7)->with('user')->first();

        if (!$employee) {
            $this->error('No se encontró empleado con ID 7 (DANIELA AIRAM)');
            return;
        }

        $this->info("=== EMPLEADO ENCONTRADO ===");
        $this->info("ID: {$employee->employee_id}");
        $this->info("Nombre: {$employee->first_name} {$employee->last_name}");
        $this->line('');

        // Buscar contratos junio 2025
        $contracts = Contract::where('advisor_id', $employee->employee_id)
            ->whereMonth('sign_date', 6)
            ->whereYear('sign_date', 2025)
            ->get();

        $this->info("=== CONTRATOS JUNIO 2025 ===");
        $this->info("Total contratos: " . $contracts->count());

        foreach ($contracts as $contract) {
            $this->line("\n--- Contrato {$contract->contract_id} ---");
            $this->line("Monto financiamiento: S/ " . number_format($contract->financing_amount, 2));
            $this->line("Plazo: {$contract->term_months} meses");
            $this->line("Fecha firma: {$contract->sign_date}");
            
            // Verificar template
            $lot = $contract->getLot();
            if ($lot && $lot->lotFinancialTemplate) {
                $template = $lot->lotFinancialTemplate;
                $this->line("Template monto: S/ " . number_format($template->financing_amount, 2));
                $this->line("Template plazo: {$template->term_months} meses");
            } else {
                $this->line("SIN TEMPLATE FINANCIERO");
            }
        }

        // Buscar comisiones existentes
        $this->line("\n=== COMISIONES EXISTENTES ===");
        $commissions = Commission::where('employee_id', $employee->employee_id)
            ->where('period_month', 6)
            ->where('period_year', 2025)
            ->orderBy('contract_id')
            ->orderBy('parent_commission_id')
            ->get();

        $this->info("Total comisiones: " . $commissions->count());

        $totalPayable = 0;
        foreach ($commissions as $commission) {
            $type = $commission->parent_commission_id ? 'HIJA' : 'PADRE';
            $payable = $commission->is_payable ? 'PAGABLE' : 'NO PAGABLE';
            
            $this->line("\n--- Comisión {$commission->commission_id} ({$type}) ---");
            $this->line("Contrato: {$commission->contract_id}");
            $this->line("Porcentaje BD: {$commission->commission_percentage}%");
            $this->line("Monto: S/ " . number_format($commission->commission_amount, 2));
            $this->line("Estado: {$payable}");
            $this->line("Ventas count: {$commission->sales_count}");
            
            if ($commission->is_payable) {
                $totalPayable += $commission->commission_amount;
            }
        }

        $this->line("\n=== RESUMEN ===");
        $this->info("Total comisiones PAGABLES: S/ " . number_format($totalPayable, 2));
        $this->info("Excel esperado: S/ 3,971.26");
        $difference = $totalPayable - 3971.26;
        $this->info("Diferencia: S/ " . number_format($difference, 2));

        // Análisis de la lógica
        $this->line("\n=== ANÁLISIS DE LÓGICA ===");
        $commissionService = app(CommissionService::class);
        
        foreach ($contracts as $contract) {
            $this->line("\n--- Analizando contrato {$contract->contract_id} ---");
            
            // Contar ventas manualmente (método privado no accesible)
            $month = $contract->sign_date->month;
            $year = $contract->sign_date->year;
            $salesCount = \Modules\Sales\Models\Contract::where('advisor_id', $contract->advisor_id)
                ->whereMonth('sign_date', $month)
                ->whereYear('sign_date', $year)
                ->where('status', 'vigente')
                ->whereNotNull('financing_amount')
                ->where('financing_amount', '>', 0)
                ->count();
            $this->line("Ventas count: {$salesCount}");
            
            // Verificar qué lógica se usaría
            $lot = $contract->getLot();
            if ($lot && $lot->lotFinancialTemplate && $lot->lotFinancialTemplate->financing_amount > 0) {
                $this->line("USARÍA TEMPLATE:");
                $template = $lot->lotFinancialTemplate;
                $ratePercent = $commissionService->getCommissionRate($salesCount, $template->term_months);
                $rateDecimal = $ratePercent / 100;
                $calculatedAmount = $template->financing_amount * $rateDecimal;
                
                $this->line("  Monto base: S/ " . number_format($template->financing_amount, 2));
                $this->line("  Tasa: {$ratePercent}% ({$rateDecimal})");
                $this->line("  Comisión: S/ " . number_format($calculatedAmount, 2));
            } else {
                $this->line("USARÍA FALLBACK (sin template):");
                // Simular la lógica del fallback corregido
                $salesCount = 3; // Ya sabemos que DANIELA tiene 3 ventas
                
                // Usar la misma lógica de tabla de rangos
                $isShortTerm = in_array($contract->term_months, [12, 24, 36]);
                if ($salesCount >= 10) {
                    $ratePercent = $isShortTerm ? 4.20 : 3.00;
                } elseif ($salesCount >= 8) {
                    $ratePercent = $isShortTerm ? 4.00 : 2.50;
                } elseif ($salesCount >= 6) {
                    $ratePercent = $isShortTerm ? 3.00 : 1.50;
                } else {
                    $ratePercent = $isShortTerm ? 2.00 : 1.00;
                }
                
                $rateDecimal = $ratePercent / 100;
                $calculatedAmount = $contract->financing_amount * $rateDecimal;

                $this->line("  Monto base: S/ " . number_format($contract->financing_amount, 2));
                $this->line("  Plazo: {$contract->term_months} meses");
                $this->line("  Ventas: {$salesCount}");
                $this->line("  Tasa: {$ratePercent}% ({$rateDecimal})");
                $this->line("  Comisión: S/ " . number_format($calculatedAmount, 2));
            }
            
            // División para < 10 ventas
            if ($salesCount < 10) {
                $firstPayment = $calculatedAmount * 0.5;
                $secondPayment = $calculatedAmount * 0.5;
                $this->line("  División 50/50:");
                $this->line("    Primer pago: S/ " . number_format($firstPayment, 2));
                $this->line("    Segundo pago: S/ " . number_format($secondPayment, 2));
            }
        }
    }
}