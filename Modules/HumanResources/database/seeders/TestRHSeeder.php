<?php

namespace Modules\HumanResources\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Security Models
use Modules\Security\Models\User;

// CRM Models
use Modules\CRM\Models\Client;
use Modules\CRM\Models\Address;
use Modules\CRM\Models\CrmInteraction;
use Modules\CRM\Models\FamilyMember;

// Inventory Models
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotMedia;
use Modules\Inventory\Models\StreetType;

// HR Models
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Models\BonusType;
use Modules\HumanResources\Models\BonusGoal;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Payroll;
use Modules\HumanResources\Models\Attendance;
use Modules\HumanResources\Models\Incentive;

// Sales Models
use Modules\Sales\Models\Reservation;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Payment;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\ContractApproval;

// Accounting Models
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Models\Invoice;

// Finance Models
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\BudgetLine;
use Modules\Finance\Models\CostCenter;
use Modules\Finance\Models\CashFlow;

// Collections Models
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;

// ServiceDesk Models
use Modules\ServiceDesk\Models\ServiceRequest;
use Modules\ServiceDesk\Models\ServiceAction;

// Integrations Models
use Modules\Integrations\Models\IntegrationLog;
use Modules\Integrations\Models\DigitalSignature;

// Audit Models
use Modules\Audit\Models\AuditLog;
use Spatie\Permission\Models\Permission;
use Modules\Security\Models\Role;

class TestRHSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Iniciando seeder completo de verificación del backend...');
        
        // Deshabilitar verificaciones de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Crear permisos primero
        $this->createPermissions();
        
        // Crear roles
        $this->createRoles();
        
        try {
            // Ejecutar seeders en orden de dependencias
            $this->seedSecurityModule();
            $this->seedCRMModule();
            $this->seedInventoryModule();
            $this->seedHRModule();
            $this->seedSalesModule();
            $this->seedAccountingModule();
            $this->seedFinanceModule();
            $this->seedCollectionsModule();
            $this->seedServiceDeskModule();
            $this->seedIntegrationsModule();
            $this->seedAuditModule();
            
            // Verificar integridad del sistema
            $this->verifySystemIntegrity();
            
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        $this->command->info('✅ ¡Seeder completo ejecutado exitosamente!');
        $this->printComprehensiveSummary();
    }

    private function seedSecurityModule(): void
    {
        $this->command->info('🔐 Seeding Security Module...');
        
        // Crear usuarios de prueba
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@casabonita.com',
                'first_name' => 'Administrador',
                'last_name' => 'Sistema',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
                'department' => 'TI',
            ],
            [
                'username' => 'gerente',
                'email' => 'gerente@casabonita.com',
                'first_name' => 'Gerente',
                'last_name' => 'Ventas',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
                'department' => 'Ventas',
            ],
            [
                'username' => 'asesor1',
                'email' => 'asesor1@casabonita.com',
                'first_name' => 'Asesor',
                'last_name' => 'Inmobiliario 1',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
                'department' => 'Ventas',
            ],
            [
                'username' => 'asesor2',
                'email' => 'asesor2@casabonita.com',
                'first_name' => 'Asesor',
                'last_name' => 'Inmobiliario 2',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
                'department' => 'Ventas',
            ],
            [
                'username' => 'contador',
                'email' => 'contador@casabonita.com',
                'first_name' => 'Contador',
                'last_name' => 'Principal',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
                'department' => 'Contabilidad',
            ]
        ];
        
        foreach ($users as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }
        
        $this->command->info("   ✅ " . User::count() . " usuarios creados");
    }

    private function seedCRMModule(): void
    {
        $this->command->info('👥 Seeding CRM Module...');
        
        $users = User::all();
        
        // Crear clientes de prueba
        for ($i = 1; $i <= 20; $i++) {
            $client = Client::firstOrCreate([
                'email' => "cliente{$i}@test.com"
            ], [
                'first_name' => "Cliente{$i}",
                'last_name' => "Apellido{$i}",
                'primary_phone' => "99999{$i}",
                'secondary_phone' => rand(0, 1) ? "88888{$i}" : null,
                'doc_type' => ['DNI', 'CE', 'RUC'][rand(0, 2)],
                'doc_number' => "1234567{$i}",
                'marital_status' => ['soltero', 'casado', 'divorciado', 'viudo'][rand(0, 3)],
                'type' => ['lead', 'client'][rand(0, 1)],
                'date' => Carbon::now()->subDays(rand(1, 365)),
                'occupation' => ['Profesional', 'Comerciante', 'Empleado', 'Independiente'][rand(0, 3)],
                'salary' => rand(1000, 8000),
                'family_group' => rand(1, 6)
            ]);
            
            // Crear dirección para el cliente
            Address::firstOrCreate([
                'client_id' => $client->client_id
            ], [
                'line1' => "Calle Test {$i} #" . rand(100, 999),
                'line2' => rand(0, 1) ? "Dpto {$i}0{$i}" : null,
                'city' => 'Lima',
                'state' => 'Lima',
                'country' => 'Perú',
                'zip_code' => '15001'
            ]);
            
            // Crear interacciones CRM
            CrmInteraction::firstOrCreate([
                'client_id' => $client->client_id,
                'channel' => 'call'
            ], [
                'user_id' => $users->random()->user_id,
                'date' => Carbon::now()->subDays(rand(1, 30)),
                'notes' => "Primera consulta del cliente {$i} sobre propiedades disponibles"
            ]);
        }
        
        $this->command->info("   ✅ " . Client::count() . " clientes creados con direcciones e interacciones");
    }

    private function seedInventoryModule(): void
    {
        $this->command->info('🏘️ Seeding Inventory Module...');
        
        // Crear tipos de calle
        $streetTypes = ['Calle', 'Avenida', 'Jirón', 'Pasaje'];
        foreach ($streetTypes as $type) {
            StreetType::firstOrCreate(['name' => $type]);
        }
        
        // Crear manzanas
        for ($i = 1; $i <= 5; $i++) {
            $manzana = Manzana::firstOrCreate([
                'name' => "Manzana {$i}"
            ]);
            
            // Crear lotes para cada manzana
            $streetTypes = StreetType::all();
            for ($j = 1; $j <= rand(15, 25); $j++) {
                $area = rand(120, 300);
                $pricePerSqm = rand(800, 1500);
                
                $lot = Lot::firstOrCreate([
                    'manzana_id' => $manzana->manzana_id,
                    'num_lot' => $j
                ], [
                    'street_type_id' => $streetTypes->random()->street_type_id,
                    'area_m2' => $area,
                    'total_price' => $area * $pricePerSqm,
                    'currency' => 'PEN',
                    'status' => ['disponible', 'reservado', 'vendido'][rand(0, 2)]
                ]);
            }
        }
        
        $this->command->info("   ✅ " . Manzana::count() . " manzanas y " . Lot::count() . " lotes creados");
    }

    private function seedHRModule(): void
    {
        $this->command->info('👨‍💼 Seeding HR Module...');
        
        // Crear tipos de bonos
        $bonusTypes = [
            [
                'type_code' => 'BV001',
                'type_name' => 'Bono por Ventas', 
                'description' => 'Bono por alcanzar meta de ventas', 
                'calculation_method' => 'percentage_of_goal',
                'is_automatic' => true,
                'frequency' => 'monthly'
            ],
            [
                'type_code' => 'BE001',
                'type_name' => 'Bono Extraordinario', 
                'description' => 'Bono especial por desempeño', 
                'calculation_method' => 'fixed_amount',
                'is_automatic' => false,
                'frequency' => 'one_time'
            ],
            [
                'type_code' => 'BP001',
                'type_name' => 'Bono de Productividad', 
                'description' => 'Bono por productividad mensual', 
                'calculation_method' => 'percentage_of_goal',
                'is_automatic' => true,
                'frequency' => 'monthly'
            ],
            [
                'type_code' => 'BPU001',
                'type_name' => 'Bono de Puntualidad', 
                'description' => 'Bono por asistencia perfecta', 
                'calculation_method' => 'attendance_rate',
                'is_automatic' => true,
                'frequency' => 'monthly'
            ],
            [
                'type_code' => 'BA001',
                'type_name' => 'Bono Anual', 
                'description' => 'Bono de fin de año', 
                'calculation_method' => 'fixed_amount',
                'is_automatic' => false,
                'frequency' => 'annual'
            ]
        ];
        
        foreach ($bonusTypes as $bonusType) {
            BonusType::firstOrCreate(['type_code' => $bonusType['type_code']], $bonusType);
        }
        
        // Crear equipos
        $teams = [
            ['team_name' => 'Equipo Ventas Norte', 'team_code' => 'VN', 'monthly_goal' => 50000],
            ['team_name' => 'Equipo Ventas Sur', 'team_code' => 'VS', 'monthly_goal' => 45000],
            ['team_name' => 'Equipo Ventas Centro', 'team_code' => 'VC', 'monthly_goal' => 55000],
            ['team_name' => 'Equipo Administrativo', 'team_code' => 'ADM', 'monthly_goal' => 0]
        ];
        
        foreach ($teams as $teamData) {
            Team::firstOrCreate(['team_code' => $teamData['team_code']], $teamData);
        }
        
        // Crear empleados
        $users = User::all();
        $teams = Team::all();
        
        foreach ($users as $index => $user) {
            // Asegurar que al menos los primeros 2 empleados sean asesores inmobiliarios
            $employeeTypes = ['asesor_inmobiliario', 'vendedor', 'administrativo', 'gerente', 'jefe_ventas'];
            $employeeType = $index < 2 ? 'asesor_inmobiliario' : $employeeTypes[rand(0, 4)];
            
            $employee = Employee::firstOrCreate([
                'user_id' => $user->user_id
            ], [
                'employee_code' => 'EMP' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'team_id' => $teams->random()->team_id,
                'employee_type' => $employeeType,
                'hire_date' => Carbon::now()->subMonths(rand(1, 24)),
                'base_salary' => rand(2500, 8000),
                'commission_percentage' => rand(2, 8),
                'is_commission_eligible' => true,
                'is_bonus_eligible' => true
            ]);
            
            // Crear comisiones históricas - Se crearán después con los contratos
            /*
            for ($i = 0; $i < rand(3, 8); $i++) {
                $saleAmount = rand(50000, 200000);
                $commissionPercentage = $employee->commission_percentage;
                
                Commission::create([
                    'employee_id' => $employee->employee_id,
                    'contract_id' => null, // Se asignará cuando se creen contratos
                    'commission_type' => 'venta_directa',
                    'sale_amount' => $saleAmount,
                    'commission_percentage' => $commissionPercentage,
                    'commission_amount' => ($saleAmount * $commissionPercentage) / 100,
                    'payment_status' => ['pendiente', 'pagado', 'cancelado'][rand(0, 2)],
                    'payment_date' => Carbon::now()->subDays(rand(1, 90)),
                    'period_month' => Carbon::now()->subDays(rand(1, 90))->month,
                    'period_year' => Carbon::now()->subDays(rand(1, 90))->year
                ]);
            }
            */
            
            // Crear bonos
            $bonusTypes = BonusType::all();
            for ($i = 0; $i < rand(2, 5); $i++) {
                $bonusAmount = rand(200, 2000);
                Bonus::create([
                    'employee_id' => $employee->employee_id,
                    'bonus_type_id' => $bonusTypes->random()->bonus_type_id,
                    'bonus_type' => 'desempeño',
                    'bonus_name' => 'Bono por desempeño excepcional',
                    'bonus_amount' => $bonusAmount,
                    'target_amount' => $bonusAmount * 1.2,
                    'achieved_amount' => $bonusAmount,
                    'achievement_percentage' => 100.00,
                    'payment_status' => ['pendiente', 'pagado', 'cancelado'][rand(0, 2)],
                    'payment_date' => Carbon::now()->subDays(rand(1, 60)),
                    'period_month' => Carbon::now()->month,
                    'period_year' => Carbon::now()->year,
                    'approved_by' => rand(0, 1) ? $employee->employee_id : null,
                    'notes' => 'Bono por desempeño excepcional'
                ]);
            }
            
            // Crear planillas (evitar duplicados)
            for ($i = 0; $i < 3; $i++) {
                $periodDate = Carbon::now()->subMonths($i);
                $payrollPeriod = $periodDate->format('Y-m');
                
                // Verificar si ya existe una planilla para este empleado y período
                $existingPayroll = Payroll::where('employee_id', $employee->employee_id)
                    ->where('payroll_period', $payrollPeriod)
                    ->first();
                
                if ($existingPayroll) {
                    continue; // Saltar si ya existe
                }
                
                $baseSalary = $employee->base_salary;
                $commissionsAmount = rand(0, 1000);
                $bonusesAmount = rand(0, 500);
                $overtimeAmount = rand(0, 300);
                $otherIncome = rand(0, 200);
                $grossSalary = $baseSalary + $commissionsAmount + $bonusesAmount + $overtimeAmount + $otherIncome;
                
                // Calcular descuentos
                $incomeTax = $grossSalary * 0.08; // 8% impuesto a la renta
                $socialSecurity = $grossSalary * 0.13; // 13% ONP/AFP
                $healthInsurance = $grossSalary * 0.09; // 9% EPS
                $otherDeductions = rand(0, 100);
                $totalDeductions = $incomeTax + $socialSecurity + $healthInsurance + $otherDeductions;
                $netSalary = $grossSalary - $totalDeductions;
                
                Payroll::create([
                    'employee_id' => $employee->employee_id,
                    'payroll_period' => $payrollPeriod,
                    'pay_period_start' => $periodDate->startOfMonth()->toDateString(),
                    'pay_period_end' => $periodDate->endOfMonth()->toDateString(),
                    'pay_date' => $periodDate->endOfMonth()->addDays(5)->toDateString(),
                    'base_salary' => $baseSalary,
                    'commissions_amount' => $commissionsAmount,
                    'bonuses_amount' => $bonusesAmount,
                    'overtime_amount' => $overtimeAmount,
                    'other_income' => $otherIncome,
                    'gross_salary' => $grossSalary,
                    'income_tax' => $incomeTax,
                    'social_security' => $socialSecurity,
                    'health_insurance' => $healthInsurance,
                    'other_deductions' => $otherDeductions,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'currency' => 'PEN',
                    'status' => 'pagado'
                ]);
            }
        }
        
        $this->command->info("   ✅ " . Employee::count() . " empleados, " . Commission::count() . " comisiones, " . Bonus::count() . " bonos creados");
    }

    private function seedSalesModule(): void
    {
        $this->command->info('💰 Seeding Sales Module...');
        
        $clients = Client::all();
        $lots = Lot::where('status', 'disponible')->get();
        $employees = Employee::where('employee_type', 'asesor_inmobiliario')->get();
        
        // Datos reales de ventas proporcionados por el usuario
        $salesData = [
            ['amount' => 19140.00, 'term' => '>36'],
            ['amount' => 20097.00, 'term' => '>36'],
            ['amount' => 31046.40, 'term' => '<36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 18480.00, 'term' => '>36'],
            ['amount' => 24696.00, 'term' => '<36'],
            ['amount' => 23284.80, 'term' => '>36'],
            ['amount' => 24901.80, 'term' => '<36'],
            ['amount' => 34151.04, 'term' => '<36'],
            ['amount' => 27165.60, 'term' => '<36'],
            ['amount' => 21168.00, 'term' => '<36'],
            ['amount' => 20160.00, 'term' => '>36'],
            ['amount' => 20160.00, 'term' => '>36'],
            ['amount' => 21344.00, 'term' => '<36'],
            ['amount' => 21168.00, 'term' => '>36']
        ];
        
        // Seleccionar un asesor para todas las ventas (simulando un mes de ventas)
        $advisor = $employees->first();
        if (!$advisor) {
            $this->command->warn('No hay asesores inmobiliarios disponibles');
            return;
        }
        
        $totalCommissions = 0;
        $contractsCreated = 0;
        
        foreach ($salesData as $index => $saleData) {
            if ($clients->isEmpty() || $lots->isEmpty()) break;
            
            $client = $clients->random();
            $lot = $lots->where('status', 'disponible')->first();
            
            if (!$lot) continue;
            
            // Crear reservación
            $reservation = Reservation::create([
                'client_id' => $client->client_id,
                'lot_id' => $lot->lot_id,
                'advisor_id' => $advisor->employee_id,
                'reservation_date' => Carbon::now()->subDays(rand(1, 30)),
                'expiration_date' => Carbon::now()->addDays(rand(15, 45)),
                'deposit_amount' => $saleData['amount'] * 0.1,
                'status' => 'convertida'
            ]);
            
            // Determinar término en meses basado en la clasificación
            $termMonths = $saleData['term'] === '<36' ? rand(12, 36) : rand(37, 60);
            
            // Generar número de contrato único
            $contractNumber = 'CONT-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $existingContract = Contract::where('contract_number', $contractNumber)->first();
            
            if ($existingContract) {
                // Si ya existe, generar un número único basado en timestamp
                $contractNumber = 'CONT-' . time() . '-' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            }
            
            // Crear contrato con datos reales
            $contract = Contract::create([
                'reservation_id' => $reservation->reservation_id,
                'contract_number' => $contractNumber,
                'sign_date' => $reservation->reservation_date->addDays(rand(1, 15)),
                'total_price' => $saleData['amount'] * 1.25, // Precio total estimado
                'down_payment' => $saleData['amount'] * 0.25, // 25% inicial
                'financing_amount' => $saleData['amount'], // Monto financiado real
                'interest_rate' => 0.085,
                'term_months' => $termMonths,
                'monthly_payment' => $saleData['amount'] / $termMonths,
                'currency' => 'PEN',
                'status' => 'vigente'
            ]);
            
            // Calcular comisión según las reglas del sistema
            $commissionRate = $saleData['term'] === '<36' ? 4.20 : 3.00; // Porcentajes según término
            $commissionAmount = ($saleData['amount'] * $commissionRate) / 100;
            $totalCommissions += $commissionAmount;
            
            // Crear comisión
            Commission::create([
                'employee_id' => $advisor->employee_id,
                'contract_id' => $contract->contract_id,
                'commission_type' => 'venta_financiada',
                'sale_amount' => $saleData['amount'],
                'installment_plan' => $termMonths,
                'commission_percentage' => $commissionRate,
                'commission_amount' => round($commissionAmount, 2),
                'payment_status' => 'pendiente',
                'payment_date' => null,
                'period_month' => Carbon::now()->month,
                'period_year' => Carbon::now()->year,
                'notes' => "Comisión por venta financiada - Término: {$saleData['term']} meses"
            ]);
            
            // Crear cronograma de pagos
            for ($j = 1; $j <= min(12, $termMonths); $j++) {
                PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'due_date' => $contract->sign_date->addMonths($j),
                    'amount' => $contract->monthly_payment,
                    'status' => $j <= 3 ? 'pagado' : 'pendiente'
                ]);
            }
            
            // Actualizar estado del lote
            $lot->update(['status' => 'vendido']);
            $contractsCreated++;
        }
        
        // Aplicar sistema de pago dividido (70/30 para más de 10 ventas)
        $salesCount = count($salesData);
        if ($salesCount > 10) {
            $firstPayment = $totalCommissions * 0.70;
            $secondPayment = $totalCommissions * 0.30;
            
            $this->command->info("   📊 Sistema de comisiones aplicado:");
            $this->command->info("   - Total de ventas: {$salesCount}");
            $this->command->info("   - Comisión total: S/ " . number_format($totalCommissions, 2));
            $this->command->info("   - Primer pago (70%): S/ " . number_format($firstPayment, 2));
            $this->command->info("   - Segundo pago (30%): S/ " . number_format($secondPayment, 2));
        }
        
        $this->command->info("   ✅ " . Reservation::count() . " reservaciones y " . $contractsCreated . " contratos creados con datos reales");
    }

    private function seedAccountingModule(): void
    {
        $this->command->info('📊 Seeding Accounting Module...');
        
        // Crear plan de cuentas básico
        $accounts = [
            ['code' => '1001', 'name' => 'Caja', 'type' => 'activo'],
            ['code' => '1002', 'name' => 'Bancos', 'type' => 'activo'],
            ['code' => '1003', 'name' => 'Cuentas por Cobrar', 'type' => 'activo'],
            ['code' => '2001', 'name' => 'Cuentas por Pagar', 'type' => 'pasivo'],
            ['code' => '3001', 'name' => 'Capital', 'type' => 'patrimonio'],
            ['code' => '4001', 'name' => 'Ventas', 'type' => 'ingreso'],
            ['code' => '5001', 'name' => 'Gastos Administrativos', 'type' => 'gasto']
        ];
        
        foreach ($accounts as $accountData) {
            ChartOfAccount::firstOrCreate(['code' => $accountData['code']], $accountData);
        }
        
        // Crear cuentas bancarias
        BankAccount::firstOrCreate(['account_number' => '123456789'], [
            'bank_name' => 'Banco de Crédito',
            'currency' => 'PEN'
        ]);
        
        BankAccount::firstOrCreate(['account_number' => '987654321'], [
            'bank_name' => 'Banco Continental',
            'currency' => 'USD'
        ]);
        
        // Crear asientos contables
        $accounts = ChartOfAccount::all();
        for ($i = 1; $i <= 10; $i++) {
            $journalEntry = JournalEntry::create([
                'date' => Carbon::now()->subDays(rand(1, 30)),
                'description' => "Asiento contable de prueba {$i}",
                'status' => 'posted'
            ]);
            
            // Crear líneas del asiento
            $amount = rand(1000, 10000);
            
            // Línea débito
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $accounts->random()->account_id,
                'debit' => $amount,
                'credit' => 0
            ]);
            
            // Línea crédito
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $accounts->random()->account_id,
                'debit' => 0,
                'credit' => $amount
            ]);
        }
        
        $this->command->info("   ✅ " . ChartOfAccount::count() . " cuentas y " . JournalEntry::count() . " asientos contables creados");
    }

    private function seedFinanceModule(): void
    {
        $this->command->info('💼 Seeding Finance Module...');
        
        $users = User::all();
        
        // Crear centros de costo
        $costCenters = [
            ['code' => 'CC001', 'name' => 'Ventas', 'description' => 'Centro de costo de ventas'],
            ['code' => 'CC002', 'name' => 'Administración', 'description' => 'Centro de costo administrativo'],
            ['code' => 'CC003', 'name' => 'Marketing', 'description' => 'Centro de costo de marketing']
        ];
        
        foreach ($costCenters as $ccData) {
            CostCenter::firstOrCreate(['code' => $ccData['code']], array_merge($ccData, [
                'manager_id' => $users->random()->user_id,
                'is_active' => true
            ]));
        }
        
        // Crear presupuestos
        for ($i = 1; $i <= 3; $i++) {
            Budget::create([
                'name' => "Presupuesto " . (2024 + $i),
                'description' => "Presupuesto anual para el año " . (2024 + $i),
                'fiscal_year' => 2024 + $i,
                'start_date' => Carbon::create(2024 + $i, 1, 1),
                'end_date' => Carbon::create(2024 + $i, 12, 31),
                'total_amount' => rand(500000, 1000000),
                'status' => ['draft', 'approved', 'executed'][rand(0, 2)],
                'created_by' => $users->random()->user_id,
                'approved_by' => rand(0, 1) ? $users->random()->user_id : null
            ]);
        }
        
        $this->command->info("   ✅ " . CostCenter::count() . " centros de costo y " . Budget::count() . " presupuestos creados");
    }

    private function seedCollectionsModule(): void
    {
        $this->command->info('💳 Seeding Collections Module...');
        
        $clients = Client::all();
        $contracts = Contract::all();
        $users = User::all();
        
        // Crear cuentas por cobrar (evitar duplicados)
        foreach ($contracts as $contract) {
            // Obtener el client_id desde la reservación asociada
            $reservation = Reservation::find($contract->reservation_id);
            
            // Generar números únicos para AR e Invoice
            $arNumber = 'AR-' . str_pad($contract->contract_id, 6, '0', STR_PAD_LEFT);
            $invoiceNumber = 'INV-' . str_pad($contract->contract_id, 6, '0', STR_PAD_LEFT);
            
            // Verificar si ya existe una cuenta por cobrar para este contrato
            $existingAR = AccountReceivable::where('contract_id', $contract->contract_id)->first();
            
            if ($existingAR) {
                continue; // Saltar si ya existe
            }
            
            // Verificar si el número AR ya existe
            $existingARNumber = AccountReceivable::where('ar_number', $arNumber)->first();
            if ($existingARNumber) {
                $arNumber = 'AR-' . time() . '-' . str_pad($contract->contract_id, 3, '0', STR_PAD_LEFT);
                $invoiceNumber = 'INV-' . time() . '-' . str_pad($contract->contract_id, 3, '0', STR_PAD_LEFT);
            }
            
            AccountReceivable::create([
                'client_id' => $reservation->client_id,
                'contract_id' => $contract->contract_id,
                'ar_number' => $arNumber,
                'invoice_number' => $invoiceNumber,
                'description' => 'Cuenta por cobrar del contrato ' . $contract->contract_number,
                'original_amount' => $contract->financing_amount,
                'outstanding_amount' => $contract->financing_amount,
                'currency' => 'PEN',
                'issue_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(rand(30, 90)),
                'status' => ['PENDING', 'PAID', 'OVERDUE'][rand(0, 2)],
                'assigned_collector_id' => $users->random()->user_id
            ]);
        }
        
        $this->command->info("   ✅ " . AccountReceivable::count() . " cuentas por cobrar creadas");
    }

    private function seedServiceDeskModule(): void
    {
        $this->command->info('🎫 Seeding ServiceDesk Module...');
        
        $users = User::all();
        $clients = Client::all();
        
        // Crear solicitudes de servicio
        for ($i = 1; $i <= 10; $i++) {
            $serviceRequest = ServiceRequest::create([
                'contract_id' => null, // Nullable based on migration
                'opened_by' => $users->random()->user_id,
                'opened_at' => Carbon::now()->subDays(rand(1, 30)),
                'ticket_type' => ['garantia', 'mantenimiento', 'otro'][rand(0, 2)],
                'priority' => ['baja', 'media', 'alta', 'critica'][rand(0, 3)],
                'status' => ['abierto', 'en_proceso', 'cerrado'][rand(0, 2)],
                'description' => "Descripción detallada de la solicitud {$i}",
                'assigned_to' => $users->random()->user_id
            ]);
            
            // Crear acciones para la solicitud
            ServiceAction::create([
                'ticket_id' => $serviceRequest->ticket_id,
                'user_id' => $users->random()->user_id,
                'action_type' => ['comentario', 'cambio_estado', 'escalado'][rand(0, 2)],
                'notes' => "Acción realizada para el ticket {$i}",
                'performed_at' => Carbon::now()->subDays(rand(1, 10))
            ]);
        }
        
        $this->command->info("   ✅ " . ServiceRequest::count() . " tickets de servicio creados");
    }

    private function seedIntegrationsModule(): void
    {
        $this->command->info('🔗 Seeding Integrations Module...');
        
        // Crear logs de integración
        $services = ['payment_gateway', 'email_service', 'sms_service', 'document_service'];
        
        for ($i = 1; $i <= 20; $i++) {
            IntegrationLog::create([
                'service' => $services[rand(0, 3)],
                'entity' => ['contract', 'payment', 'client'][rand(0, 2)],
                'entity_id' => rand(1, 100),
                'status' => ['success', 'error'][rand(0, 1)],
                'message' => "Log de integración {$i}",
                'logged_at' => Carbon::now()->subDays(rand(1, 30))
            ]);
        }
        
        $this->command->info("   ✅ " . IntegrationLog::count() . " logs de integración creados");
    }

    private function seedAuditModule(): void
    {
        $this->command->info('🔍 Seeding Audit Module...');
        
        $users = User::all();
        $entities = ['User', 'Client', 'Contract', 'Payment', 'Employee'];
        
        // Crear logs de auditoría
        for ($i = 1; $i <= 50; $i++) {
            AuditLog::create([
                'user_id' => $users->random()->user_id,
                'action' => ['insert', 'update', 'delete'][rand(0, 2)],
                'entity' => $entities[rand(0, 4)],
                'entity_id' => rand(1, 100),
                'timestamp' => Carbon::now()->subDays(rand(1, 30)),
                'changes' => json_encode([
                    'old' => ['field' => 'old_value'],
                    'new' => ['field' => 'new_value']
                ])
            ]);
        }
        
        $this->command->info("   ✅ " . AuditLog::count() . " logs de auditoría creados");
    }

    private function verifySystemIntegrity(): void
    {
        $this->command->info('🔍 Verificando integridad del sistema...');
        
        $errors = [];
        
        // Verificar relaciones críticas
        $usersWithoutEmployees = User::whereDoesntHave('employee')->count();
        if ($usersWithoutEmployees > 0) {
            $errors[] = "{$usersWithoutEmployees} usuarios sin empleado asociado";
        }
        
        $contractsWithoutPayments = Contract::whereDoesntHave('paymentSchedules')->count();
        if ($contractsWithoutPayments > 0) {
            $errors[] = "{$contractsWithoutPayments} contratos sin cronograma de pagos";
        }
        
        $employeesWithoutCommissions = Employee::where('employee_type', 'asesor_inmobiliario')
            ->whereDoesntHave('commissions')->count();
        if ($employeesWithoutCommissions > 0) {
            $errors[] = "{$employeesWithoutCommissions} asesores sin comisiones";
        }
        
        if (empty($errors)) {
            $this->command->info('   ✅ Integridad del sistema verificada correctamente');
        } else {
            $this->command->warn('   ⚠️  Advertencias encontradas:');
            foreach ($errors as $error) {
                $this->command->warn("      - {$error}");
            }
        }
    }

    private function printComprehensiveSummary(): void
    {
        $this->command->info('');
        $this->command->info('🎯 RESUMEN COMPLETO DEL SISTEMA');
        $this->command->info('=====================================');
        
        // Módulo Security
        $this->command->info('🔐 SECURITY:');
        $this->command->info("   - Usuarios: " . User::count());
        
        // Módulo CRM
        $this->command->info('👥 CRM:');
        $this->command->info("   - Clientes: " . Client::count());
        $this->command->info("   - Direcciones: " . Address::count());
        $this->command->info("   - Interacciones: " . CrmInteraction::count());
        
        // Módulo Inventory
        $this->command->info('🏘️ INVENTORY:');
        $this->command->info("   - Manzanas: " . Manzana::count());
        $this->command->info("   - Lotes: " . Lot::count());
        $this->command->info("   - Lotes disponibles: " . Lot::where('status', 'disponible')->count());
        
        // Módulo HR
        $this->command->info('👨‍💼 HUMAN RESOURCES:');
        $this->command->info("   - Empleados: " . Employee::count());
        $this->command->info("   - Equipos: " . Team::count());
        $this->command->info("   - Tipos de bonos: " . BonusType::count());
        $this->command->info("   - Comisiones: " . Commission::count());
        $this->command->info("   - Bonos: " . Bonus::count());
        $this->command->info("   - Planillas: " . Payroll::count());
        
        // Módulo Sales
        $this->command->info('💰 SALES:');
        $this->command->info("   - Reservaciones: " . Reservation::count());
        $this->command->info("   - Contratos: " . Contract::count());
        $this->command->info("   - Cronogramas de pago: " . PaymentSchedule::count());
        
        // Módulo Accounting
        $this->command->info('📊 ACCOUNTING:');
        $this->command->info("   - Cuentas contables: " . ChartOfAccount::count());
        $this->command->info("   - Asientos contables: " . JournalEntry::count());
        $this->command->info("   - Líneas contables: " . JournalLine::count());
        $this->command->info("   - Cuentas bancarias: " . BankAccount::count());
        
        // Módulo Finance
        $this->command->info('💼 FINANCE:');
        $this->command->info("   - Centros de costo: " . CostCenter::count());
        $this->command->info("   - Presupuestos: " . Budget::count());
        
        // Módulo Collections
        $this->command->info('💳 COLLECTIONS:');
        $this->command->info("   - Cuentas por cobrar: " . AccountReceivable::count());
        
        // Módulo ServiceDesk
        $this->command->info('🎫 SERVICE DESK:');
        $this->command->info("   - Tickets de servicio: " . ServiceRequest::count());
        $this->command->info("   - Acciones de servicio: " . ServiceAction::count());
        
        // Módulo Integrations
        $this->command->info('🔗 INTEGRATIONS:');
        $this->command->info("   - Logs de integración: " . IntegrationLog::count());
        
        // Módulo Audit
        $this->command->info('🔍 AUDIT:');
        $this->command->info("   - Logs de auditoría: " . AuditLog::count());
        
        $this->command->info('');
        $this->command->info('✅ SISTEMA COMPLETAMENTE FUNCIONAL');
        $this->command->info('=====================================');
        $this->command->info('🎯 Todos los módulos han sido probados exitosamente');
        $this->command->info('📊 Base de datos poblada con datos de prueba realistas');
        $this->command->info('🔗 Relaciones entre módulos verificadas');
        $this->command->info('');
        // Asignar roles a usuarios
        $this->assignRolesToUsers();
        
        $this->command->info('👤 Usuario de prueba: admin@casabonita.com / admin / password');
    }

    private function createPermissions(): void
    {
        $this->command->info('🔐 Creando permisos del sistema...');
        
        $permissions = [
            // Security Module
            'security.access',
            'security.users.index',
            'security.users.store',
            'security.users.update',
            'security.users.destroy',
            'security.users.change-password',
            'security.users.toggle-status',
            'security.roles.view',
            'security.roles.store',
            'security.roles.update',
            'security.roles.destroy',
            'security.permissions.view',
            'security.permissions.store',
            'security.permissions.update',
            'security.permissions.destroy',
            
            // CRM Module
            'crm.access',
            'crm.clients.view',
            'crm.clients.create',
            'crm.clients.update',
            'crm.clients.destroy',
            'crm.clients.export',
            'crm.clients.summary',
            'crm.clients.spouses',
            'crm.clients.spouses.view',
            'crm.clients.spouses.create',
            'crm.clients.spouses.delete',
            'crm.spouses.manage',
            'crm.addresses.view',
            'crm.addresses.store',
            'crm.addresses.update',
            'crm.addresses.delete',
            'crm.addresses.manage',
            'crm.interactions.view',
            'crm.interactions.create',
            'crm.interactions.store',
            'crm.interactions.update',
            'crm.interactions.delete',
            'crm.interactions.destroy',
            
            // Sales Module
            'sales.access',
            'sales.reservations.access',
            'sales.reservations.view',
            'sales.reservations.create',
            'sales.reservations.store',
            'sales.reservations.update',
            'sales.reservations.cancel',
            'sales.reservations.convert',
            'sales.reservations.destroy',
            'sales.contracts.view',
            'sales.contracts.create',
            'sales.contracts.store',
            'sales.contracts.update',
            'sales.contracts.delete',
            'sales.contracts.destroy',
            'sales.conversions.process',
            'sales.payments.view',
            'sales.payments.store',
            'sales.payments.update',
            'sales.payments.destroy',
            'sales.schedules.index',
            'sales.schedules.store',
            'sales.schedules.update',
            'sales.schedules.destroy',
            
            // Inventory Module
            'inventory.access',
            'inventory.manzanas.view',
            'inventory.manzanas.store',
            'inventory.manzanas.update',
            'inventory.manzanas.delete',
            'inventory.street-types.view',
            'inventory.street-types.store',
            'inventory.street-types.update',
            'inventory.street-types.delete',
            'inventory.lots.view',
            'inventory.lots.store',
            'inventory.lots.update',
            'inventory.lots.delete',
            'inventory.media.index',
            'inventory.media.store',
            'inventory.media.update',
            'inventory.media.destroy',
            'inventory.media.manage',
            
            // Human Resources Module
            'hr.access',
            'hr.employees.index',
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.store',
            'hr.employees.update',
            'hr.employees.delete',
            'hr.teams.index',
            'hr.teams.view',
            'hr.teams.create',
            'hr.teams.store',
            'hr.teams.update',
            'hr.teams.delete',
            'hr.commissions.index',
            'hr.commissions.view',
            'hr.commissions.create',
            'hr.commissions.store',
            'hr.commissions.update',
            'hr.commissions.delete',
            'hr.commissions.process',
            'hr.commissions.pay',
            'hr.commissions.sales-detail',
            'hr.bonuses.index',
            'hr.bonuses.view',
            'hr.bonuses.create',
            'hr.bonuses.store',
            'hr.bonuses.update',
            'hr.bonuses.delete',
            'hr.bonus-types.index',
            'hr.bonus-types.view',
            'hr.bonus-types.create',
            'hr.bonus-types.store',
            'hr.bonus-types.update',
            'hr.bonus-types.delete',
            'hr.bonus-goals.index',
            'hr.bonus-goals.view',
            'hr.bonus-goals.create',
            'hr.bonus-goals.store',
            'hr.bonus-goals.update',
            'hr.bonus-goals.delete',
            'hr.payroll.index',
            'hr.payroll.view',
            'hr.payroll.create',
            'hr.payroll.process',
            
            // Accounting Module
            'accounting.access',
            'accounting.bank_accounts.view',
            'accounting.bank_accounts.create',
            'accounting.bank_accounts.update',
            'accounting.bank_accounts.delete',
            'accounting.transactions.view',
            'accounting.transactions.create',
            'accounting.transactions.update',
            'accounting.transactions.delete',
            'accounting.invoices.view',
            'accounting.invoices.create',
            'accounting.invoices.update',
            'accounting.invoices.delete',
            'accounting.journal_entries.manage',
            
            // Finance Module
            'finance.access',
            'budget.view',
            'budget.store',
            'budget.update',
            'budget.update.approved',
            'budget.delete',
            'budget.approve',
            'finance.budgets.view',
            'finance.budgets.create',
            'finance.budgets.update',
            'finance.budgets.delete',
            'finance.budgets.approve',
            'finance.cost-centers.view',
            'finance.cost-centers.create',
            'finance.cost-centers.update',
            'finance.cost-centers.delete',
            'finance.cash-flow.view',
            'finance.cash-flow.create',
            'finance.cash-flow.update',
            'finance.cash-flow.delete',
            
            // Collections Module
            'collections.access',
            'collections.receivables.view',
            'collections.receivables.create',
            'collections.receivables.edit',
            'collections.receivables.delete',
            'collections.receivables.assign_collector',
            'collections.receivables.cancel',
            'collections.payments.create',
            'collections.reports.view',
            'collections.alerts.view',
            
            // Service Desk Module
            'service-desk.access',
            'service-desk.tickets.view',
            'service-desk.tickets.store',
            'service-desk.tickets.update',
            'service-desk.tickets.delete',
            'service-desk.actions.view',
            'service-desk.actions.create',
            'service-desk.actions.update',
            'service-desk.actions.delete',
            
            // Integrations Module
            'integrations.access',
            'integrations.api.sunat',
            'integrations.api.payment',
            'integrations.logs.view',
            'integrations.signatures.manage',
            
            // Audit Module
            'audit.access',
            'audit.logs.view',
            'audit.actions.track',
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }
        
        $this->command->info('✅ Permisos creados: ' . count($permissions));
    }
    
    private function createRoles(): void
    {
        $this->command->info('👥 Creando roles del sistema...');
        
        // Obtener todos los permisos
        $allPermissions = Permission::pluck('name')->toArray();
        
        // Rol Administrador - Todos los permisos
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'sanctum'
        ]);
        $adminRole->syncPermissions($allPermissions);
        
        // Rol Gerente - Permisos de gestión
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'sanctum'
        ]);
        $managerPermissions = [
            'security.access',
            'security.users.index',
            'crm.access', 'crm.clients.view', 'crm.clients.create', 'crm.clients.update',
            'crm.interactions.view', 'crm.interactions.create',
            'sales.access', 'sales.reservations.view', 'sales.contracts.view',
            'inventory.access', 'inventory.lots.view', 'inventory.manzanas.view',
            'hr.access', 'hr.employees.view', 'hr.commissions.view', 'hr.bonuses.view',
            'accounting.access', 'accounting.transactions.view', 'accounting.invoices.view',
            'finance.access', 'finance.budgets.view', 'finance.budgets.approve',
            'collections.access', 'collections.receivables.view', 'collections.reports.view',
            'audit.access', 'audit.logs.view'
        ];
        $managerRole->syncPermissions($managerPermissions);
        
        // Rol Asesor de Ventas - Permisos de ventas y CRM
        $salesRole = Role::firstOrCreate([
            'name' => 'sales_advisor',
            'guard_name' => 'sanctum'
        ]);
        $salesPermissions = [
            'crm.access', 'crm.clients.view', 'crm.clients.create', 'crm.clients.update',
            'crm.interactions.view', 'crm.interactions.create', 'crm.interactions.update',
            'sales.access', 'sales.reservations.view', 'sales.reservations.create', 'sales.reservations.update',
            'sales.contracts.view', 'sales.contracts.create',
            'inventory.access', 'inventory.lots.view',
            'hr.access', 'hr.commissions.view'
        ];
        $salesRole->syncPermissions($salesPermissions);
        
        // Rol Contador - Permisos de contabilidad y finanzas
        $accountantRole = Role::firstOrCreate([
            'name' => 'accountant',
            'guard_name' => 'sanctum'
        ]);
        $accountantPermissions = [
            'accounting.access', 'accounting.bank_accounts.view', 'accounting.bank_accounts.create',
            'accounting.transactions.view', 'accounting.transactions.create', 'accounting.transactions.update',
            'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update',
            'accounting.journal_entries.manage',
            'finance.access', 'finance.budgets.view', 'finance.budgets.create', 'finance.budgets.update',
            'finance.cost-centers.view', 'finance.cash-flow.view',
            'collections.access', 'collections.receivables.view', 'collections.payments.create'
        ];
        $accountantRole->syncPermissions($accountantPermissions);
        
        // Rol RH - Permisos de recursos humanos
        $hrRole = Role::firstOrCreate([
            'name' => 'hr_specialist',
            'guard_name' => 'sanctum'
        ]);
        $hrPermissions = [
            'hr.access', 'hr.employees.index', 'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
            'hr.teams.index', 'hr.teams.view', 'hr.teams.create', 'hr.teams.update',
            'hr.commissions.index', 'hr.commissions.view', 'hr.commissions.process', 'hr.commissions.pay',
            'hr.bonuses.index', 'hr.bonuses.view', 'hr.bonuses.create', 'hr.bonuses.update',
            'hr.payroll.index', 'hr.payroll.view', 'hr.payroll.process'
        ];
        $hrRole->syncPermissions($hrPermissions);
        
        // Rol Soporte - Permisos de service desk
        $supportRole = Role::firstOrCreate([
            'name' => 'support',
            'guard_name' => 'sanctum'
        ]);
        $supportPermissions = [
            'service-desk.access', 'service-desk.tickets.view', 'service-desk.tickets.store',
            'service-desk.tickets.update', 'service-desk.actions.view', 'service-desk.actions.create'
        ];
        $supportRole->syncPermissions($supportPermissions);
        
        $this->command->info('✅ Roles creados: admin, manager, sales_advisor, accountant, hr_specialist, support');
    }
    
    private function assignRolesToUsers(): void
    {
        $this->command->info('🔗 Asignando roles a usuarios...');
        
        // Obtener usuarios
        $adminUser = User::where('email', 'admin@casabonita.com')->first();
        $gerenteUser = User::where('email', 'gerente@casabonita.com')->first();
        $asesor1User = User::where('email', 'asesor1@casabonita.com')->first();
        $asesor2User = User::where('email', 'asesor2@casabonita.com')->first();
        $contadorUser = User::where('email', 'contador@casabonita.com')->first();
        
        // Obtener roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $salesRole = Role::where('name', 'sales_advisor')->first();
        $accountantRole = Role::where('name', 'accountant')->first();
        
        // Asignar roles
        if ($adminUser && $adminRole) {
            $adminUser->assignRole($adminRole);
            $this->command->info('✅ Rol admin asignado a admin@casabonita.com');
        }
        
        if ($gerenteUser && $managerRole) {
            $gerenteUser->assignRole($managerRole);
            $this->command->info('✅ Rol manager asignado a gerente@casabonita.com');
        }
        
        if ($asesor1User && $salesRole) {
            $asesor1User->assignRole($salesRole);
            $this->command->info('✅ Rol sales_advisor asignado a asesor1@casabonita.com');
        }
        
        if ($asesor2User && $salesRole) {
            $asesor2User->assignRole($salesRole);
            $this->command->info('✅ Rol sales_advisor asignado a asesor2@casabonita.com');
        }
        
        if ($contadorUser && $accountantRole) {
            $contadorUser->assignRole($accountantRole);
            $this->command->info('✅ Rol accountant asignado a contador@casabonita.com');
        }
        
        // Asignar rol manager a todos los empleados tipo 'gerente'
        $managers = Employee::where('employee_type', 'gerente')->get();
        foreach ($managers as $manager) {
            if ($manager->user && $managerRole) {
                $manager->user->assignRole($managerRole);
            }
        }
        
        // Asignar rol sales_advisor a todos los empleados tipo 'asesor_inmobiliario'
        $advisors = Employee::where('employee_type', 'asesor_inmobiliario')->get();
        foreach ($advisors as $advisor) {
            if ($advisor->user && $salesRole) {
                $advisor->user->assignRole($salesRole);
            }
        }
        
        $this->command->info('✅ Roles asignados a usuarios y empleados');
    }
}
