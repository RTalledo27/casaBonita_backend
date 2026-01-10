<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\ContractCreated;
use App\Events\PaymentRecorded;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

class TestEventsCommand extends Command
{
    protected $signature = 'events:test {--event=all : Which event to test (all, contract, payment)}';
    protected $description = 'Probar que los eventos y listeners están funcionando correctamente';

    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('   🧪 PRUEBA DE EVENTOS Y LISTENERS');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        $eventType = $this->option('event');

        // Verificar corte actual antes de la prueba
        $cutBefore = $this->getCurrentCut();
        $this->info('📊 Estado del corte ANTES de la prueba:');
        $this->displayCut($cutBefore);
        $this->newLine();

        if ($eventType === 'all' || $eventType === 'contract') {
            $this->testContractEvent();
        }

        if ($eventType === 'all' || $eventType === 'payment') {
            $this->testPaymentEvent();
        }

        // Verificar corte después de la prueba
        sleep(1); // Dar tiempo para que se procese el evento
        $cutAfter = $this->getCurrentCut();
        
        $this->newLine();
        $this->info('📊 Estado del corte DESPUÉS de la prueba:');
        $this->displayCut($cutAfter);
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->analyzeResults($cutBefore, $cutAfter);
        $this->info('═══════════════════════════════════════════════════════════════');

        return 0;
    }

    private function testContractEvent(): void
    {
        $this->info('🔥 Disparando evento: ContractCreated');
        
        try {
            // Obtener un contrato real usando el modelo
            $contract = Contract::first();
            
            if (!$contract) {
                $this->error('   ❌ No hay contratos en la base de datos para probar');
                return;
            }

            $this->line("   📝 Usando contrato ID: {$contract->contract_id}");
            $this->line("   📝 Cliente: {$contract->client_name}");
            $this->line("   📝 Precio: S/ " . number_format($contract->total_price ?? 0, 2));
            
            // Disparar el evento con el objeto completo
            event(new ContractCreated($contract));
            
            $this->info('   ✅ Evento ContractCreated disparado');
            $this->comment('   💡 El listener UpdateTodaySalesCut@handleContractCreated debería ejecutarse');
            
        } catch (\Exception $e) {
            $this->error('   ❌ Error al disparar evento: ' . $e->getMessage());
            $this->line('   Stack trace: ' . $e->getTraceAsString());
        }
        
        $this->newLine();
    }

    private function testPaymentEvent(): void
    {
        $this->info('🔥 Disparando evento: PaymentRecorded');
        
        try {
            // Obtener un pago real de la BD
            $payment = DB::table('payments')->first();
            
            if (!$payment) {
                $this->warn('   ⚠️  No hay pagos en la base de datos');
                $this->comment('   💡 Creando datos de prueba...');
                
                // Crear un array con estructura de pago de prueba
                $paymentData = [
                    'payment_id' => 999999,
                    'contract_id' => 1,
                    'amount' => 1000.00,
                    'payment_date' => Carbon::now()->format('Y-m-d'),
                ];
                
                $this->line("   📝 Usando pago de prueba ID: 999999");
            } else {
                $paymentData = (array) $payment;
                $this->line("   📝 Usando pago ID: {$payment->payment_id}");
            }
            
            // Disparar el evento
            event(new PaymentRecorded(
                $paymentData['payment_id'],
                $paymentData['contract_id'] ?? 1,
                $paymentData['amount'] ?? 0,
                $paymentData['payment_date'] ?? Carbon::now()->format('Y-m-d')
            ));
            
            $this->info('   ✅ Evento PaymentRecorded disparado');
            $this->comment('   💡 El listener UpdateTodaySalesCut@handlePaymentRecorded debería ejecutarse');
            
        } catch (\Exception $e) {
            $this->error('   ❌ Error al disparar evento: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function getCurrentCut()
    {
        return DB::table('sales_cuts')
            ->whereDate('cut_date', Carbon::today())
            ->first();
    }

    private function displayCut($cut): void
    {
        if (!$cut) {
            $this->warn('   ⚠️  No existe corte para hoy');
            return;
        }

        $this->line("   📅 Fecha: {$cut->cut_date}");
        $this->line("   📊 Ventas: {$cut->total_sales_count}");
        $this->line("   💵 Pagos: {$cut->total_payments_count}");
        $this->line("   💰 Ingresos: S/ " . number_format($cut->total_revenue, 2));
        $this->line("   📝 Actualizado: {$cut->updated_at}");
    }

    private function analyzeResults($before, $after): void
    {
        if (!$before || !$after) {
            $this->error('❌ No se pudo comparar - falta el corte de hoy');
            return;
        }

        $salesChanged = $after->total_sales_count != $before->total_sales_count;
        $paymentsChanged = $after->total_payments_count != $before->total_payments_count;
        $revenueChanged = $after->total_revenue != $before->total_revenue;
        $updatedAtChanged = $after->updated_at != $before->updated_at;

        $this->info('🔍 ANÁLISIS DE RESULTADOS:');
        $this->newLine();

        if ($salesChanged) {
            $diff = $after->total_sales_count - $before->total_sales_count;
            $this->info("   ✅ Ventas incrementaron: +$diff (de {$before->total_sales_count} a {$after->total_sales_count})");
        } else {
            $this->warn('   ⚠️  Ventas NO cambiaron');
        }

        if ($paymentsChanged) {
            $diff = $after->total_payments_count - $before->total_payments_count;
            $this->info("   ✅ Pagos incrementaron: +$diff (de {$before->total_payments_count} a {$after->total_payments_count})");
        } else {
            $this->warn('   ⚠️  Pagos NO cambiaron');
        }

        if ($revenueChanged) {
            $diff = $after->total_revenue - $before->total_revenue;
            $this->info("   ✅ Ingresos incrementaron: +S/ " . number_format($diff, 2));
        } else {
            $this->warn('   ⚠️  Ingresos NO cambiaron');
        }

        if ($updatedAtChanged) {
            $this->info("   ✅ updated_at fue actualizado ({$before->updated_at} → {$after->updated_at})");
        } else {
            $this->error('   ❌ updated_at NO cambió - El listener NO se ejecutó');
        }

        $this->newLine();

        if ($updatedAtChanged && ($salesChanged || $paymentsChanged || $revenueChanged)) {
            $this->info('🎉 RESULTADO: Los eventos y listeners están funcionando correctamente');
        } elseif ($updatedAtChanged) {
            $this->warn('⚠️  RESULTADO: Los listeners se ejecutan pero puede haber un problema con la lógica');
        } else {
            $this->error('❌ RESULTADO: Los listeners NO se están ejecutando');
            $this->newLine();
            $this->comment('💡 Posibles causas:');
            $this->line('   1. EventServiceProvider no está registrado en config/app.php');
            $this->line('   2. Los listeners no están en el namespace correcto');
            $this->line('   3. La cola (queue) está configurada pero no hay workers corriendo');
            $this->line('   4. Los eventos no están siendo despachados correctamente');
            $this->newLine();
            $this->comment('🔧 Soluciones:');
            $this->line('   1. Verifica: php artisan event:list');
            $this->line('   2. Limpia cache: php artisan event:cache o php artisan optimize:clear');
            $this->line('   3. Verifica EventServiceProvider.php');
        }
    }
}
