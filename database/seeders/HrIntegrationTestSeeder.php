<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\CRM\Models\Client;
use Modules\Collections\Models\CustomerPayment;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Security\Models\User;
use Illuminate\Support\Facades\Hash;

class HrIntegrationTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('Creando datos de prueba para integración HR-Collections...');
            
            // Crear algunos empleados de prueba si no existen
            $employees = [];
            for ($i = 1; $i <= 3; $i++) {
                // Crear usuario primero
                $user = User::firstOrCreate(
                    ['email' => 'empleado' . $i . '@casabonita.com'],
                    [
                        'username' => 'empleado' . $i,
                        'password_hash' => Hash::make('password123'),
                        'status' => 'active',
                    ]
                );
                
                $employee = Employee::firstOrCreate(
                    ['employee_code' => 'EMP00' . $i],
                    [
                        'user_id' => $user->user_id,
                        'employee_type' => 'asesor_inmobiliario',
                        'base_salary' => 1500.00,
                        'commission_percentage' => 3.00,
                        'is_commission_eligible' => true,
                        'employment_status' => 'activo',
                        'hire_date' => Carbon::now()->subMonths(6)
                    ]
                );
                $employees[] = $employee;
                $this->command->info("Empleado creado: {$employee->employee_code}");
            }

            // Crear algunos clientes de prueba si no existen
            $clients = [];
            for ($i = 1; $i <= 5; $i++) {
                $client = Client::firstOrCreate(
                    ['email' => 'cliente' . $i . '@test.com'],
                    [
                        'first_name' => 'Cliente',
                        'last_name' => 'Test ' . $i,
                        'primary_phone' => 999888777,
                        'doc_type' => 'DNI',
                        'doc_number' => '1234567' . $i,
                        'type' => 'client'
                    ]
                );
                $clients[] = $client;
                $this->command->info("Cliente creado: {$client->first_name} {$client->last_name}");
            }

            $this->command->info('✅ Datos básicos creados exitosamente.');
            $this->command->info('   - Empleados: ' . count($employees));
            $this->command->info('   - Clientes: ' . count($clients));
            $this->command->info('');
            $this->command->info('ℹ️  Para crear comisiones, primero necesitas:');
            $this->command->info('   1. Crear reservaciones en el sistema');
            $this->command->info('   2. Crear contratos asociados a las reservaciones');
            $this->command->info('   3. Luego las comisiones se generarán automáticamente');
            $this->command->info('');
            $this->command->info('💡 El dashboard HR Integration ahora tiene empleados y clientes de prueba.');

            DB::commit();
            
            $this->command->info('✅ Datos de prueba para integración HR-Collections creados exitosamente.');
            $this->command->info("📊 Resumen:");
            $this->command->info("   - Empleados: " . count($employees));
            $this->command->info("   - Clientes: " . count($clients));
            $this->command->info('');
            $this->command->info('🎯 Próximos pasos para completar el sistema:');
            $this->command->info('   1. Crear lotes y proyectos');
            $this->command->info('   2. Crear reservaciones');
            $this->command->info('   3. Crear contratos');
            $this->command->info('   4. Las comisiones se generarán automáticamente');
            $this->command->info('   5. Configurar pagos de clientes en el módulo Collections');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error al crear datos de prueba: ' . $e->getMessage());
            throw $e;
        }
    }
}