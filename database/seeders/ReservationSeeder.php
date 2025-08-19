<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si existen datos base necesarios
        $clientCount = DB::table('clients')->count();
        $lotCount = DB::table('lots')->count();
        $employeeCount = DB::table('employees')->count();

        if ($clientCount == 0 || $lotCount == 0 || $employeeCount == 0) {
            $this->command->info('No hay suficientes datos base para crear reservations.');
            $this->command->info("Clients: $clientCount, Lots: $lotCount, Employees: $employeeCount");
            return;
        }

        // Obtener algunos IDs existentes
        $clientIds = DB::table('clients')->limit(3)->pluck('client_id')->toArray();
        $lotIds = DB::table('lots')->limit(3)->pluck('lot_id')->toArray();
        $employeeIds = DB::table('employees')->limit(3)->pluck('employee_id')->toArray();

        // Crear reservations de prueba
        for ($i = 0; $i < 3; $i++) {
            DB::table('reservations')->insert([
                'client_id' => $clientIds[$i % count($clientIds)],
                'lot_id' => $lotIds[$i % count($lotIds)],
                'advisor_id' => $employeeIds[$i % count($employeeIds)],
                'reservation_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
                'expiration_date' => Carbon::now()->addDays(rand(15, 45))->format('Y-m-d'),
                'deposit_amount' => rand(1000, 5000),
                'status' => 'pendiente_pago',
            ]);
        }

        $this->command->info('Se crearon 3 reservations de prueba.');
    }
}
