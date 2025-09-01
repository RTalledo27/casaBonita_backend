<?php

namespace Modules\HumanResources\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\CRM\Models\Client;
use Modules\Collections\Models\CustomerPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrIntegrationTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            // Crear algunos empleados de prueba si no existen
            $employees = [];
            for ($i = 1; $i <= 3; $i++) {
                $employee = Employee::firstOrCreate(
                    ['employee_code' => 'EMP00' . $i],
                    [
                        'user_id' => null,
                        'employee_type' => 'asesor_inmobiliario',
                        'base_salary' => 1500.00,
                        'commission_percentage' => 3.00,
                        'is_commission_eligible' => true,
                        'employment_status' => 'activo',
                        'hire_date' => Carbon::now()->subMonths(6)
                    ]
                );
                $employees[] = $employee;
            }

            // Crear algunos clientes de prueba si no existen
            $clients = [];
            for ($i = 1; $i <= 5; $i++) {
                $client = Client::firstOrCreate(
                    ['email' => 'cliente' . $i . '@test.com'],
                    [
                        'first_name' => 'Cliente',
                        'last_name' => 'Test ' . $i,
                        'phone' => '999888777',
                        'document_type' => 'DNI',
                        'document_number' => '1234567' . $i,
                        'status' => 'active'
                    ]
                );
                $clients[] = $client;
            }

            // Crear comisiones de prueba con diferentes estados
            $verificationStatuses = ['pending', 'verified', 'failed'];
            $paymentStatuses = ['pending', 'eligible', 'paid'];
            
            foreach ($employees as $employee) {
                for ($i = 0; $i < 5; $i++) {
                    $client = $clients[array_rand($clients)];
                    $verificationStatus = $verificationStatuses[array_rand($verificationStatuses)];
                    $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                    
                    Commission::create([
                        'employee_id' => $employee->employee_id,
                        'contract_id' => null, // Puede ser null para pruebas
                        'commission_type' => 'sale',
                        'sale_amount' => rand(50000, 200000),
                        'commission_percentage' => 3.00,
                        'commission_amount' => rand(1500, 6000),
                        'payment_status' => $paymentStatus,
                        'period_month' => Carbon::now()->month,
                        'period_year' => Carbon::now()->year,
                        'status' => 'generated',
                        // Campos para integración HR-Collections
                        'verification_status' => $verificationStatus,
                        'customer_id' => $client->client_id,
                        'period_start' => Carbon::now()->startOfMonth(),
                        'period_end' => Carbon::now()->endOfMonth(),
                        'verified_at' => $verificationStatus === 'verified' ? Carbon::now() : null,
                        'verified_amount' => $verificationStatus === 'verified' ? rand(1500, 6000) : null
                    ]);
                }
            }

            // Crear algunos pagos de clientes de prueba
            foreach ($clients as $client) {
                for ($i = 0; $i < 3; $i++) {
                    CustomerPayment::create([
                        'client_id' => $client->client_id,
                        'payment_number' => 'PAY-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                        'payment_date' => Carbon::now()->subDays(rand(1, 30)),
                        'amount' => rand(1000, 5000),
                        'currency' => 'PEN',
                        'payment_method' => 'TRANSFER',
                        'reference_number' => 'REF-' . rand(100000, 999999),
                        'notes' => 'Pago de prueba para integración HR-Collections'
                    ]);
                }
            }

            DB::commit();
            
            $this->command->info('Datos de prueba para integración HR-Collections creados exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error al crear datos de prueba: ' . $e->getMessage());
        }
    }
}