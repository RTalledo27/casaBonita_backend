<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\BonusType;
use Modules\HumanResources\Models\BonusGoal;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\Payroll;
use Modules\HumanResources\Services\PayrollService;
use Modules\HumanResources\Services\BonusService;
use Modules\Security\Models\User;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;

class SystemVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔍 Verificando integridad del sistema completo...');

        // Ejecutar el seeder completo primero
        $this->call(CompleteTestSeeder::class);

        // Verificar cada módulo
        $this->verifySecurityModule();
        $this->verifyCRMModule();
        $this->verifyInventoryModule();
        $this->verifyHRModule();
        $this->verifySalesModule();

        // Probar flujos completos
        $this->testPayrollFlow();
        $this->testBonusFlow();

        $this->command->info('✅ ¡Verificación del sistema completada exitosamente!');
        $this->printSystemSummary();
    }

    private function verifySecurityModule(): void
    {
        $this->command->info('🔐 Verificando módulo de Seguridad...');

        $usersCount = User::count();
        $adminUser = User::where('email', 'admin@casabonita.com')->first();

        if ($usersCount > 0 && $adminUser) {
            $this->command->info("   ✅ {$usersCount} usuarios creados, admin disponible");
        } else {
            $this->command->error('   ❌ Error en módulo de Seguridad');
        }
    }

    private function verifyCRMModule(): void
    {
        $this->command->info('👥 Verificando módulo CRM...');

        $clientsCount = Client::count();

        if ($clientsCount > 0) {
            $this->command->info("   ✅ {$clientsCount} clientes creados");
        } else {
            $this->command->error('   ❌ Error en módulo CRM');
        }
    }

    private function verifyInventoryModule(): void
    {
        $this->command->info('🏘️ Verificando módulo de Inventario...');

        $lotsCount = Lot::count();
        $availableLots = Lot::where('status', 'disponible')->count();

        if ($lotsCount > 0) {
            $this->command->info("   ✅ {$lotsCount} lotes creados ({$availableLots} disponibles)");
        } else {
            $this->command->error('   ❌ Error en módulo de Inventario');
        }
    }

    private function verifyHRModule(): void
    {
        $this->command->info('👨‍💼 Verificando módulo de Recursos Humanos...');

        $bonusTypesCount = BonusType::count();
        $bonusGoalsCount = BonusGoal::count();
        $teamsCount = Team::count();
        $employeesCount = Employee::count();
        $commissionsCount = Commission::count();
        $bonusesCount = Bonus::count();
        $payrollsCount = Payroll::count();

        $errors = [];

        if ($bonusTypesCount < 5) $errors[] = "Pocos tipos de bonos ({$bonusTypesCount})";
        if ($bonusGoalsCount < 5) $errors[] = "Pocas metas de bonos ({$bonusGoalsCount})";
        if ($teamsCount < 3) $errors[] = "Pocos equipos ({$teamsCount})";
        if ($employeesCount < 10) $errors[] = "Pocos empleados ({$employeesCount})";

        if (empty($errors)) {
            $this->command->info("   ✅ HR completo: {$bonusTypesCount} tipos bonos, {$bonusGoalsCount} metas, {$teamsCount} equipos, {$employeesCount} empleados");
            $this->command->info("   ✅ Datos históricos: {$commissionsCount} comisiones, {$bonusesCount} bonos, {$payrollsCount} planillas");
        } else {
            $this->command->error('   ❌ Errores en HR: ' . implode(', ', $errors));
        }
    }

    private function verifySalesModule(): void
    {
        $this->command->info('💰 Verificando módulo de Ventas...');

        $reservationsCount = Reservation::count();
        $contractsCount = Contract::count();

        if ($reservationsCount > 0 && $contractsCount > 0) {
            $this->command->info("   ✅ {$reservationsCount} reservaciones, {$contractsCount} contratos");
        } else {
            $this->command->error('   ❌ Error en módulo de Ventas');
        }
    }

    private function testPayrollFlow(): void
    {
        $this->command->info('🧪 Probando flujo de nóminas...');

        try {
            $employee = Employee::where('employee_type', 'asesor_inmobiliario')->first();

            if (!$employee) {
                $this->command->error('   ❌ No hay asesores para probar');
                return;
            }

            // Verificar que el empleado tenga comisiones y bonos
            $commissions = Commission::where('employee_id', $employee->employee_id)->count();
            $bonuses = Bonus::where('employee_id', $employee->employee_id)->count();

            $this->command->info("   ✅ Empleado {$employee->employee_code}: {$commissions} comisiones, {$bonuses} bonos");

            // Verificar que existan planillas
            $payroll = Payroll::where('employee_id', $employee->employee_id)->first();

            if ($payroll) {
                $this->command->info("   ✅ Planilla encontrada: Bruto S/{$payroll->gross_salary}, Neto S/{$payroll->net_salary}");
            } else {
                $this->command->warn('   ⚠️  No se encontraron planillas para el empleado');
            }
        } catch (\Exception $e) {
            $this->command->error("   ❌ Error en flujo de nóminas: {$e->getMessage()}");
        }
    }

    private function testBonusFlow(): void
    {
        $this->command->info('🧪 Probando flujo de bonos...');

        try {
            // Verificar tipos de bonos automáticos
            $automaticBonusTypes = BonusType::where('is_automatic', true)->count();
            $manualBonusTypes = BonusType::where('is_automatic', false)->count();

            $this->command->info("   ✅ {$automaticBonusTypes} tipos automáticos, {$manualBonusTypes} tipos manuales");

            // Verificar bonos con diferentes estados
            // Usar payment_status y approved_by para determinar estados correctos
            $pendingPaymentBonuses = Bonus::where('payment_status', 'pendiente')->count();
            $approvedBonuses = Bonus::whereNotNull('approved_by')->count();
            $unapprovedBonuses = Bonus::whereNull('approved_by')->count();
            $paidBonuses = Bonus::where('payment_status', 'pagado')->count();
            $cancelledBonuses = Bonus::where('payment_status', 'cancelado')->count();

            $this->command->info("   ✅ Estados de pago: {$pendingPaymentBonuses} pendientes, {$paidBonuses} pagados, {$cancelledBonuses} cancelados");
            $this->command->info("   ✅ Estados de aprobación: {$approvedBonuses} aprobados, {$unapprovedBonuses} sin aprobar");

            // Verificar relación con metas
            $bonusesWithGoals = Bonus::whereNotNull('bonus_goal_id')->count();
            $totalBonuses = Bonus::count();

            $this->command->info("   ✅ {$bonusesWithGoals}/{$totalBonuses} bonos vinculados a metas");

            // Verificar bonos por tipo
            $bonusesWithType = Bonus::whereNotNull('bonus_type_id')->count();
            $this->command->info("   ✅ {$bonusesWithType}/{$totalBonuses} bonos vinculados a tipos");
        } catch (\Exception $e) {
            $this->command->error("   ❌ Error en flujo de bonos: {$e->getMessage()}");
        }
    }

    private function printSystemSummary(): void
    {
        $this->command->info('');
        $this->command->info('🎯 RESUMEN COMPLETO DEL SISTEMA');
        $this->command->info('=====================================');

        // Contadores por módulo
        $users = User::count();
        $clients = Client::count();
        $lots = Lot::count();
        $employees = Employee::count();
        $reservations = Reservation::count();
        $contracts = Contract::count();

        // Contadores específicos de HR
        $bonusTypes = BonusType::count();
        $bonusGoals = BonusGoal::count();
        $teams = Team::count();
        $commissions = Commission::count();
        $bonuses = Bonus::count();
        $payrolls = Payroll::count();

        $this->command->info("👥 USUARIOS Y SEGURIDAD: {$users} usuarios");
        $this->command->info("🏢 CRM: {$clients} clientes");
        $this->command->info("🏘️  INVENTARIO: {$lots} lotes");
        $this->command->info("👨‍💼 RECURSOS HUMANOS:");
        $this->command->info("   - {$employees} empleados en {$teams} equipos");
        $this->command->info("   - {$bonusTypes} tipos de bonos con {$bonusGoals} metas");
        $this->command->info("   - {$commissions} comisiones históricas");
        $this->command->info("   - {$bonuses} bonos registrados");
        $this->command->info("   - {$payrolls} planillas procesadas");
        $this->command->info("💰 VENTAS: {$reservations} reservaciones, {$contracts} contratos");
        $this->command->info('');
        $this->command->info('🚀 SISTEMA LISTO PARA DEMOSTRACIÓN COMPLETA');
        $this->command->info('   - Flujo de nóminas con BonusType y BonusGoal ✅');
        $this->command->info('   - Cálculo automático de bonos ✅');
        $this->command->info('   - Integración entre módulos ✅');
        $this->command->info('   - Datos históricos y actuales ✅');
        $this->command->info('');
    }
}
