<?php

namespace Modules\Collections\Console\Commands;

use Illuminate\Console\Command;
use Modules\Collections\Services\PaymentScheduleGenerationService;

class GeneratePaymentSchedulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collections:generate-schedules 
                            {--payment-type=installments : Tipo de pago (cash|installments)}
                            {--installments=24 : Número de cuotas para pagos a plazos}
                            {--start-date= : Fecha de inicio (YYYY-MM-DD)}
                            {--dry-run : Ejecutar sin hacer cambios reales}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera cronogramas de pagos masivamente para contratos activos sin cronograma';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando generación masiva de cronogramas de pagos...');
        $this->newLine();

        try {
            $scheduleGenerationService = app(PaymentScheduleGenerationService::class);
            
            // Obtener opciones del comando
            $options = [
                'payment_type' => $this->option('payment-type'),
                'installments' => (int) $this->option('installments'),
                'start_date' => $this->option('start-date'),
                'dry_run' => $this->option('dry-run')
            ];

            // Mostrar configuración
            $this->table(
                ['Configuración', 'Valor'],
                [
                    ['Tipo de pago', $options['payment_type']],
                    ['Número de cuotas', $options['installments']],
                    ['Fecha de inicio', $options['start_date'] ?: 'Automática'],
                    ['Modo de prueba', $options['dry_run'] ? 'Sí' : 'No']
                ]
            );
            $this->newLine();

            if ($options['dry_run']) {
                $this->warn('⚠️  MODO DE PRUEBA ACTIVADO - No se realizarán cambios reales');
                $this->newLine();
            }

            // Confirmar ejecución
            if (!$this->confirm('¿Desea continuar con la generación de cronogramas?')) {
                $this->info('Operación cancelada.');
                return 0;
            }

            // Ejecutar generación
            $result = $scheduleGenerationService->generateBulkPaymentSchedules($options);

            // Mostrar resultados
            $this->newLine();
            $this->info('✅ ' . $result['message']);
            $this->newLine();

            // Tabla de estadísticas
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Contratos procesados', $result['processed']],
                    ['Cronogramas generados', count($result['results'])],
                    ['Errores encontrados', count($result['errors'])]
                ]
            );

            // Mostrar errores si los hay
            if (!empty($result['errors'])) {
                $this->newLine();
                $this->error('❌ Errores encontrados:');
                foreach ($result['errors'] as $error) {
                    $this->line("  • Contrato {$error['contract_id']}: {$error['error']}");
                }
            }

            // Mostrar algunos resultados exitosos
            if (!empty($result['results'])) {
                $this->newLine();
                $this->info('📋 Cronogramas generados exitosamente:');
                $count = 0;
                foreach ($result['results'] as $contractResult) {
                    if ($count >= 5) {
                        $remaining = count($result['results']) - 5;
                        $this->line("  ... y {$remaining} más");
                        break;
                    }
                    $this->line("  • Contrato {$contractResult['contract_id']}: {$contractResult['schedules_count']} cuotas generadas");
                    $count++;
                }
            }

            $this->newLine();
            $this->info('🎉 Proceso completado exitosamente!');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la generación: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}