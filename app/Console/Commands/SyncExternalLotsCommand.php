<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Services\ExternalLotImportService;
use App\Services\LogicwareApiService;

/**
 * Comando para sincronizar lotes desde el API externa de LOGICWARE CRM
 * 
 * Uso:
 * php artisan lots:sync-external              # Sincronizar todos los lotes
 * php artisan lots:sync-external --code=E2-02 # Sincronizar un lote específico
 * php artisan lots:sync-external --test       # Probar conexión sin importar
 */
class SyncExternalLotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lots:sync-external
                            {--code= : Código específico del lote a sincronizar (Ej: E2-02)}
                            {--test : Solo probar la conexión con el API sin importar datos}
                            {--force : Forzar importación sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar lotes desde el API externa de LOGICWARE CRM';

    protected LogicwareApiService $apiService;
    protected ExternalLotImportService $importService;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->apiService = app(LogicwareApiService::class);
        $this->importService = app(ExternalLotImportService::class);

        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║     SINCRONIZACIÓN DE LOTES - API EXTERNA LOGICWARE CRM       ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Modo test - solo verificar conexión
        if ($this->option('test')) {
            return $this->testConnection();
        }

        // Sincronizar un lote específico
        if ($code = $this->option('code')) {
            return $this->syncSpecificLot($code);
        }

        // Sincronizar todos los lotes
        return $this->syncAllLots();
    }

    /**
     * Probar la conexión con el API
     */
    protected function testConnection(): int
    {
        $this->info('🔍 Probando conexión con API externa...');
        $this->newLine();

        try {
            $this->info('  ⏳ Obteniendo propiedades de prueba...');
            $properties = $this->apiService->getProperties(['limit' => 5]);
            
            if (isset($properties['data'])) {
                $total = count($properties['data']);
                $this->info("  ✅ Conexión exitosa - {$total} propiedades obtenidas");
                $this->newLine();

                if ($total > 0) {
                    $this->info('  📋 Ejemplo de propiedades:');
                    foreach (array_slice($properties['data'], 0, 3) as $property) {
                        $code = $property['code'] ?? 'N/A';
                        $status = $property['status'] ?? 'N/A';
                        $price = $property['price'] ?? 'N/A';
                        $this->line("     • Código: {$code} | Estado: {$status} | Precio: {$price}");
                    }
                }
            }

            $this->newLine();
            $this->info('✅ Test de conexión completado exitosamente');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error en test de conexión:');
            $this->error('   ' . $e->getMessage());
            $this->newLine();
            
            return Command::FAILURE;
        }
    }

    /**
     * Sincronizar un lote específico
     */
    protected function syncSpecificLot(string $code): int
    {
        $this->info("🔄 Sincronizando lote específico: {$code}");
        $this->newLine();

        try {
            if (!$this->option('force')) {
                if (!$this->confirm('¿Desea continuar con la sincronización?', true)) {
                    $this->warn('Operación cancelada');
                    return Command::SUCCESS;
                }
            }

            $this->info('  ⏳ Procesando...');
            $result = $this->importService->syncLotByCode($code);

            if ($result['success']) {
                $this->newLine();
                $this->info('✅ ' . $result['message']);
                
                if (isset($result['stats'])) {
                    $this->displayStats($result['stats']);
                }
                
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error('❌ Error: ' . $result['message']);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error sincronizando lote:');
            $this->error('   ' . $e->getMessage());
            $this->newLine();
            
            return Command::FAILURE;
        }
    }

    /**
     * Sincronizar todos los lotes
     */
    protected function syncAllLots(): int
    {
        $this->info('🔄 Sincronizando TODOS los lotes desde API externa');
        $this->newLine();

        try {
            // Obtener cantidad aproximada
            $this->info('  ⏳ Obteniendo información del API...');
            $preview = $this->apiService->getAvailableProperties();
            $total = isset($preview['data']) ? count($preview['data']) : 0;
            
            $this->info("  📊 Total de propiedades disponibles: {$total}");
            $this->newLine();

            if ($total === 0) {
                $this->warn('⚠️  No hay propiedades disponibles para importar');
                return Command::SUCCESS;
            }

            if (!$this->option('force')) {
                if (!$this->confirm("¿Desea importar {$total} lotes?", true)) {
                    $this->warn('Operación cancelada');
                    return Command::SUCCESS;
                }
            }

            $this->newLine();
            $this->info('  ⏳ Iniciando importación...');
            $this->newLine();

            $progressBar = $this->output->createProgressBar($total);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->setMessage('Procesando...');
            $progressBar->start();

            $result = $this->importService->importLots([
                'callback' => function() use ($progressBar) {
                    $progressBar->advance();
                }
            ]);

            $progressBar->finish();
            $this->newLine(2);

            if ($result['success']) {
                $this->info('✅ Importación completada exitosamente');
                $this->newLine();
                $this->displayStats($result['stats']);
                
                if (!empty($result['errors'])) {
                    $this->displayErrors($result['errors']);
                }
                
                return Command::SUCCESS;
            } else {
                $this->error('❌ Importación completada con errores');
                $this->newLine();
                $this->displayStats($result['stats']);
                $this->displayErrors($result['errors']);
                
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error durante la importación:');
            $this->error('   ' . $e->getMessage());
            $this->newLine();
            
            return Command::FAILURE;
        }
    }

    /**
     * Mostrar estadísticas de la importación
     */
    protected function displayStats(array $stats): void
    {
        $this->info('📊 ESTADÍSTICAS:');
        $this->line('  ┌─────────────────────────────────┐');
        $this->line(sprintf('  │ Total procesados:    %10d │', $stats['total'] ?? 0));
        $this->line(sprintf('  │ Creados:             %10d │', $stats['created'] ?? 0));
        $this->line(sprintf('  │ Actualizados:        %10d │', $stats['updated'] ?? 0));
        $this->line(sprintf('  │ Omitidos:            %10d │', $stats['skipped'] ?? 0));
        $this->line(sprintf('  │ Errores:             %10d │', $stats['errors'] ?? 0));
        $this->line('  └─────────────────────────────────┘');
        $this->newLine();
    }

    /**
     * Mostrar errores
     */
    protected function displayErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $this->warn('⚠️  ERRORES ENCONTRADOS:');
        $this->newLine();
        
        foreach (array_slice($errors, 0, 10) as $index => $error) {
            $this->line('  ' . ($index + 1) . '. ' . $error);
        }
        
        if (count($errors) > 10) {
            $remaining = count($errors) - 10;
            $this->line("  ... y {$remaining} errores más");
        }
        
        $this->newLine();
    }
}
