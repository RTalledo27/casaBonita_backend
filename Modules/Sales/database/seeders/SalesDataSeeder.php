<?php

namespace Modules\Sales\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\CRM\Models\Client;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;
use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;

class SalesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos necesarios
        $lots = Lot::where('status', 'disponible')->take(20)->get();
        $clients = Client::take(15)->get();
        $advisors = Employee::where('employee_type', 'asesor_inmobiliario')->get();

        if ($lots->isEmpty() || $clients->isEmpty() || $advisors->isEmpty()) {
            $this->command->warn('⚠️  Faltan datos previos (lotes, clientes o asesores). Ejecuta primero los seeders de Inventory, CRM y HumanResources.');
            return;
        }

        $reservationStatuses = ['pendiente_pago', 'completada', 'cancelada', 'convertida'];
        $contractStatuses = ['pendiente_aprobacion', 'vigente', 'resuelto', 'cancelado'];

        // Array para rastrear ventas por asesor (para calcular comisiones por volumen)
        $advisorSales = [];

        // Crear reservaciones
        foreach ($lots->take(15) as $index => $lot) {
            $client = $clients->random();
            $advisor = $advisors->random();

            $reservationDate = Carbon::now()->subDays(rand(1, 90));
            $expirationDate = $reservationDate->copy()->addDays(30);

            $reservation = Reservation::create([
                'lot_id' => $lot->lot_id,
                'client_id' => $client->client_id,
                'advisor_id' => $advisor->employee_id,
                'reservation_date' => $reservationDate,
                'expiration_date' => $expirationDate,
                'deposit_amount' => rand(1000, 5000),
                'status' => $reservationStatuses[array_rand($reservationStatuses)]
            ]);

            // Crear contratos para algunas reservaciones
            if ($index < 10 && in_array($reservation->status, ['convertida', 'completada'])) {
                $totalPrice = $lot->total_price;
                $downPayment = $totalPrice * 0.2; // 20% de enganche
                $financingAmount = $totalPrice * 0.8; // 80% financiado
                $interestRate = rand(8, 15) / 100; // 8-15% anual
                $termMonths = rand(12, 60); // 1-5 años

                // Calcular pago mensual usando fórmula de amortización
                $monthlyRate = $interestRate / 12;
                $monthlyPayment = $financingAmount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / (pow(1 + $monthlyRate, $termMonths) - 1);

                $contract = Contract::create([
                    'reservation_id' => $reservation->reservation_id,
                    'advisor_id' => $advisor->employee_id,
                    'contract_number' => 'CONT-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'sign_date' => $reservationDate->copy()->addDays(rand(1, 15)),
                    'total_price' => $totalPrice,
                    'currency' => 'MXN',
                    'down_payment' => $downPayment,
                    'financing_amount' => $financingAmount,
                    'interest_rate' => $interestRate,
                    'term_months' => $termMonths,
                    'monthly_payment' => round($monthlyPayment, 2),
                    'status' => $contractStatuses[array_rand($contractStatuses)]
                ]);

                // Rastrear ventas por asesor
                if (!isset($advisorSales[$advisor->employee_id])) {
                    $advisorSales[$advisor->employee_id] = 0;
                }
                $advisorSales[$advisor->employee_id]++;

                // Crear comisión basada en la tabla proporcionada
                $this->createCommission($contract, $advisor, $advisorSales[$advisor->employee_id]);

                // Actualizar estado del lote
                $lot->update(['status' => 'reservado']);
            }
        }

        $this->command->info('✅ Datos de ventas y comisiones creados exitosamente');
    }

    /**
     * Crear comisión basada en la tabla de comisiones por ventas financiadas
     */
    private function createCommission(Contract $contract, Employee $advisor, int $salesCount): void
    {
        // Determinar el tipo de plazo basado en term_months
        $isShortTerm = in_array($contract->term_months, [12, 24, 36]);
        $installmentPlan = $isShortTerm ? 'short_term' : 'long_term';

        // Calcular porcentaje de comisión según la tabla
        $commissionPercentage = $this->calculateCommissionPercentage($salesCount, $isShortTerm);

        // Calcular monto de comisión sobre el monto financiado
        $commissionAmount = $contract->financing_amount * ($commissionPercentage / 100);

        // Determinar el período (mes y año del contrato)
        $signDate = Carbon::parse($contract->sign_date);

        Commission::create([
            'employee_id' => $advisor->employee_id,
            'contract_id' => $contract->contract_id,
            'commission_type' => 'venta_financiada',
            'sale_amount' => $contract->financing_amount, // Usar monto financiado como base
            'installment_plan' => $contract->term_months,
            'commission_percentage' => $commissionPercentage,
            'commission_amount' => round($commissionAmount, 2),
            'payment_status' => rand(0, 1) ? 'pendiente' : 'pagado', // Aleatorio para el seeder
            'payment_date' => rand(0, 1) ? $signDate->copy()->addDays(rand(15, 45)) : null,
            'period_month' => $signDate->month,
            'period_year' => $signDate->year,
            'notes' => "Comisión por venta financiada - {$salesCount} ventas acumuladas"
        ]);
    }

    /**
     * Calcular porcentaje de comisión según la tabla proporcionada
     */
    private function calculateCommissionPercentage(int $salesCount, bool $isShortTerm): float
    {
        // Tabla de comisiones según la imagen proporcionada
        $commissionTable = [
            'short_term' => [ // Plazo 12/24/36
                10 => 4.20, // >= 10 ventas
                8 => 4.00,  // >= 8 ventas
                6 => 3.00,  // >= 6 ventas
                'default' => 2.00 // < 6 ventas
            ],
            'long_term' => [ // Plazo 48/60
                10 => 3.00, // >= 10 ventas
                8 => 2.50,  // >= 8 ventas
                6 => 1.50,  // >= 6 ventas
                'default' => 1.00 // < 6 ventas
            ]
        ];

        $planType = $isShortTerm ? 'short_term' : 'long_term';
        $table = $commissionTable[$planType];

        if ($salesCount >= 10) return $table[10];
        if ($salesCount >= 8) return $table[8];
        if ($salesCount >= 6) return $table[6];
        return $table['default'];
    }
}
