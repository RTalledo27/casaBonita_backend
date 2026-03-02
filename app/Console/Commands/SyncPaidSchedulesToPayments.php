<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Payment;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\Contract;

class SyncPaidSchedulesToPayments extends Command
{
    protected $signature = 'payments:sync-from-schedules 
                            {--contract= : Sincronizar solo un contrato específico (contract_id)}
                            {--dry-run : Solo mostrar lo que se haría, sin ejecutar cambios}
                            {--force : No pedir confirmación}';

    protected $description = 'Crea registros de pago para cuotas marcadas como pagadas que no tienen pago registrado (reparación de importaciones)';

    public function handle()
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   SINCRONIZACIÓN: Cronogramas Pagados → Pagos Registrados   ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $contractId = $this->option('contract');

        if ($isDryRun) {
            $this->warn('🔍 MODO DRY-RUN: No se realizarán cambios en la base de datos');
            $this->newLine();
        }

        // 1. Buscar cuotas pagadas SIN registro de pago
        $query = PaymentSchedule::where('status', 'pagado')
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.schedule_id', 'payment_schedules.schedule_id');
            });

        if ($contractId) {
            $query->where('contract_id', $contractId);
            $this->info("📋 Filtrando por contrato ID: {$contractId}");
        }

        $schedulesWithoutPayment = $query->with('contract')->get();

        $totalFound = $schedulesWithoutPayment->count();

        if ($totalFound === 0) {
            $this->info('✅ No se encontraron cuotas pagadas sin registro de pago. Todo está sincronizado.');
            return 0;
        }

        $this->warn("⚠️  Se encontraron {$totalFound} cuotas marcadas como PAGADAS sin registro de pago.");
        $this->newLine();

        // 2. Mostrar resumen por contrato
        $byContract = $schedulesWithoutPayment->groupBy('contract_id');
        
        $tableData = [];
        foreach ($byContract as $cId => $schedules) {
            $contract = $schedules->first()->contract;
            $totalAmount = $schedules->sum(function ($s) {
                return $s->amount_paid ?? $s->amount;
            });
            
            $tableData[] = [
                'Contrato ID' => $cId,
                'Nro. Contrato' => $contract->contract_number ?? 'N/A',
                'Cuotas sin pago' => $schedules->count(),
                'Monto total' => number_format($totalAmount, 2),
            ];
        }
        
        $this->table(
            ['Contrato ID', 'Nro. Contrato', 'Cuotas sin pago', 'Monto total'],
            $tableData
        );
        $this->newLine();

        // 3. Mostrar detalle de cuotas
        if ($totalFound <= 50 || $this->option('verbose')) {
            $detailData = [];
            foreach ($schedulesWithoutPayment as $schedule) {
                $detailData[] = [
                    $schedule->schedule_id,
                    $schedule->contract_id,
                    $schedule->installment_number,
                    $schedule->type ?? 'N/A',
                    number_format($schedule->amount, 2),
                    number_format($schedule->amount_paid ?? $schedule->amount, 2),
                    $schedule->paid_date ?? $schedule->due_date ?? 'N/A',
                ];
            }
            
            $this->table(
                ['Schedule ID', 'Contrato', 'Cuota #', 'Tipo', 'Monto', 'Monto Pagado', 'Fecha Pago'],
                $detailData
            );
            $this->newLine();
        }

        if ($isDryRun) {
            $this->info("🔍 Dry-run completado. Se crearían {$totalFound} registros de pago.");
            return 0;
        }

        // 4. Confirmar ejecución
        if (!$this->option('force')) {
            if (!$this->confirm("¿Deseas crear {$totalFound} registros de pago para estas cuotas?")) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        // 5. Crear pagos
        $this->newLine();
        $bar = $this->output->createProgressBar($totalFound);
        $bar->start();

        $created = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            foreach ($schedulesWithoutPayment as $schedule) {
                try {
                    $paymentAmount = $schedule->amount_paid ?? $schedule->logicware_paid_amount ?? $schedule->amount;
                    $paymentDate = $schedule->paid_date ?? $schedule->payment_date ?? $schedule->due_date ?? now()->toDateString();

                    // Verificar una vez más que no exista (concurrencia)
                    $exists = Payment::where('schedule_id', $schedule->schedule_id)
                        ->where('contract_id', $schedule->contract_id)
                        ->exists();

                    if ($exists) {
                        $bar->advance();
                        continue;
                    }

                    Payment::create([
                        'schedule_id' => $schedule->schedule_id,
                        'contract_id' => $schedule->contract_id,
                        'payment_date' => $paymentDate,
                        'amount' => $paymentAmount,
                        'method' => 'importacion_logicware',
                        'reference' => 'SYNC-REPAIR-' . ($schedule->contract->contract_number ?? $schedule->contract_id) . '-C' . $schedule->installment_number
                    ]);

                    $created++;

                    Log::info('[SyncPaidSchedules] Pago creado', [
                        'schedule_id' => $schedule->schedule_id,
                        'contract_id' => $schedule->contract_id,
                        'installment' => $schedule->installment_number,
                        'amount' => $paymentAmount,
                        'date' => $paymentDate
                    ]);

                } catch (\Exception $e) {
                    $errors++;
                    Log::error('[SyncPaidSchedules] Error creando pago', [
                        'schedule_id' => $schedule->schedule_id,
                        'error' => $e->getMessage()
                    ]);
                }

                $bar->advance();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error("❌ Error crítico: " . $e->getMessage());
            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        // 6. Resumen final
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║           RESUMEN DE OPERACIÓN           ║');
        $this->info('╠══════════════════════════════════════════╣');
        $this->info("║  Cuotas procesadas:  {$totalFound}");
        $this->info("║  Pagos creados:      {$created}");
        if ($errors > 0) {
            $this->error("║  Errores:            {$errors}");
        }
        $this->info('╚══════════════════════════════════════════╝');
        $this->newLine();

        if ($created > 0) {
            $this->info("✅ Se crearon {$created} registros de pago exitosamente.");
            $this->info("   Los pagos ahora aparecerán en el módulo de Pagos.");
        }

        return 0;
    }
}
