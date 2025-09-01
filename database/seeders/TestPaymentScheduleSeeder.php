<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CRM\Models\Client;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

class TestPaymentScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existen cronogramas de pago
        $existingSchedules = PaymentSchedule::count();
        
        if ($existingSchedules > 0) {
            $this->command->info('Ya existen cronogramas de pago en la base de datos.');
            return;
        }
        
        // Verificar que existe el contrato con ID 1
        $contract = Contract::find(1);
        if (!$contract) {
            $this->command->error('No existe el contrato con ID 1. Verifica que exista un contrato válido.');
            return;
        }
        
        // Crear cronogramas de pago adicionales para el contrato existente
        for ($i = 1; $i <= 6; $i++) {
            PaymentSchedule::create([
                'contract_id' => 1,
                'installment_number' => $i,
                'due_date' => Carbon::now()->addMonths($i),
                'amount' => 1000.00 + ($i * 100),
                'status' => $i <= 2 ? 'pagado' : 'pendiente',
                'notes' => 'Cuota de prueba #' . $i,
            ]);
        }
        
        $this->command->info('Cronogramas de pago de prueba creados exitosamente para el contrato ID 1.');
    }
}
