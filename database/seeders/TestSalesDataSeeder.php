<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CRM\Models\Client;
use Modules\Sales\Models\Reservation;
use Modules\Sales\Models\Contract;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\HumanResources\Models\Employee;
use Modules\Security\Models\User;
use Carbon\Carbon;

class TestSalesDataSeeder extends Seeder
{
    public function run()
    {
        // Crear algunos lotes de prueba si no existen
        $this->createTestLots();
        
        // Crear algunos empleados de prueba si no existen
        $this->createTestEmployees();
        
        // Crear reservaciones y contratos de prueba
        $this->createTestSalesData();
    }
    
    private function createTestLots()
    {
        // Crear manzana de prueba si no existe
        $manzana = Manzana::firstOrCreate(
            ['name' => 'A'],
            ['name' => 'A']
        );
        
        // Obtener el primer street_type disponible
        $streetType = \Modules\Inventory\Models\StreetType::first();
        
        // Crear lotes de prueba
        for ($i = 1; $i <= 5; $i++) {
            Lot::firstOrCreate(
                ['num_lot' => "10{$i}"],
                [
                    'num_lot' => "10{$i}",
                    'manzana_id' => $manzana->manzana_id,
                    'street_type_id' => $streetType ? $streetType->street_type_id : null,
                    'area_m2' => 120.00 + ($i * 10),
                    'total_price' => (120.00 + ($i * 10)) * 500.00,
                    'currency' => 'USD',
                    'status' => 'disponible'
                ]
            );
        }
        
        $this->command->info('Lotes de prueba creados/verificados.');
    }
    
    private function createTestEmployees()
    {
        // Crear usuario de prueba para el empleado
        $user = User::firstOrCreate(
            ['email' => 'asesor.test@casabonita.com'],
            [
                'username' => 'asesor.test',
                'first_name' => 'Asesor',
                'last_name' => 'Test',
                'email' => 'asesor.test@casabonita.com',
                'password_hash' => bcrypt('password'),
                'status' => 'active'
            ]
        );
        
        // Crear empleado asesor de prueba
        Employee::firstOrCreate(
            ['employee_code' => 'EMP0001'],
            [
                'user_id' => $user->user_id,
                'employee_code' => 'EMP0001',
                'employee_type' => 'asesor_inmobiliario',
                'base_salary' => 1500.00,
                'commission_percentage' => 3.00,
                'is_commission_eligible' => true,
                'employment_status' => 'activo',
                'hire_date' => Carbon::now()->subMonths(6)
            ]
        );
        
        $this->command->info('Empleados de prueba creados/verificados.');
    }
    
    private function createTestSalesData()
    {
        $clients = Client::take(4)->get();
        $lots = Lot::where('status', 'disponible')->take(3)->get();
        $employee = Employee::where('employee_code', 'EMP0001')->first();
        
        if ($clients->isEmpty() || $lots->isEmpty() || !$employee) {
            $this->command->warn('No hay suficientes datos para crear ventas de prueba.');
            return;
        }
        
        foreach ($lots as $index => $lot) {
            $client = $clients->get($index % $clients->count());
            
            // Crear reservación
            $reservation = Reservation::create([
                'lot_id' => $lot->lot_id,
                'client_id' => $client->client_id,
                'advisor_id' => $employee->employee_id,
                'reservation_date' => Carbon::now()->subDays(rand(10, 60)),
                'expiration_date' => Carbon::now()->addDays(30),
                'deposit_amount' => 1000.00,
                'status' => 'completada'
            ]);
            
            // Crear contrato
            Contract::create([
                'reservation_id' => $reservation->reservation_id,
                'contract_number' => 'CT-TEST-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'total_price' => $lot->total_price,
                'down_payment' => $lot->total_price * 0.20,
                'financing_amount' => $lot->total_price * 0.80,
                'interest_rate' => 0.12,
                'term_months' => 60,
                'monthly_payment' => ($lot->total_price * 0.80 * 1.12) / 60,
                'currency' => 'USD',
                'status' => 'vigente',
                'sign_date' => Carbon::now()->subDays(rand(5, 30))
            ]);
            
            // Actualizar estado del lote
            $lot->update(['status' => 'vendido']);
        }
        
        $this->command->info('Datos de ventas de prueba creados exitosamente.');
    }
}