<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Services\ExternalLotImportService;

class TestSalesImportCommand extends Command
{
    protected $signature = 'sales:test-import 
                            {--start= : Fecha inicio (YYYY-MM-DD)}
                            {--end= : Fecha fin (YYYY-MM-DD)}
                            {--force : Forzar refresh del API}
                            {--dry-run : Solo mostrar datos sin importar}';

    protected $description = 'Probar importación de ventas desde LOGICWARE';

    public function handle()
    {
        $this->info('🔄 Iniciando prueba de importación de ventas desde LOGICWARE...');
        $this->newLine();

        $start = $this->option('start') ?? now()->startOfMonth()->toDateString();
        $end = $this->option('end') ?? now()->endOfMonth()->toDateString();
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->table(['Parámetro', 'Valor'], [
            ['Fecha inicio', $start],
            ['Fecha fin', $end],
            ['Force refresh', $force ? 'Sí' : 'No'],
            ['Dry run', $dryRun ? 'Sí' : 'No']
        ]);

        try {
            /** @var ExternalLotImportService $importService */
            $importService = app(ExternalLotImportService::class);

            if ($dryRun) {
                $this->info('📋 Modo DRY-RUN: Solo mostrando datos sin importar');
                $this->newLine();

                // Obtener datos del API
                $apiService = app(\App\Services\LogicwareApiService::class);
                $salesData = $apiService->getSales($start, $end, $force);

                if (empty($salesData['data'])) {
                    $this->warn('⚠️  No hay ventas en el rango de fechas especificado');
                    return 0;
                }

                $this->info("✅ Se encontraron " . count($salesData['data']) . " ventas");
                $this->newLine();

                // Mostrar preview de las primeras 3 ventas
                foreach (array_slice($salesData['data'], 0, 3) as $index => $sale) {
                    $this->info("Venta #" . ($index + 1));
                    $this->line("  📄 Documento: " . ($sale['documentNumber'] ?? 'N/A'));
                    $this->line("  👤 Cliente: " . ($sale['fullName'] ?? 'N/A'));
                    $this->line("  📧 Email: " . ($sale['email'] ?? 'N/A'));
                    $this->line("  📞 Teléfono: " . ($sale['phone'] ?? 'N/A'));
                    
                    if (!empty($sale['documents'])) {
                        $this->line("  📋 Contratos: " . count($sale['documents']));
                        
                        foreach ($sale['documents'] as $doc) {
                            $this->line("    • Correlativo: " . ($doc['correlative'] ?? 'N/A'));
                            $this->line("      Asesor: " . ($doc['seller'] ?? 'N/A'));
                            $this->line("      Estado: " . ($doc['status'] ?? 'N/A'));
                            
                            if (!empty($doc['units'])) {
                                foreach ($doc['units'] as $unit) {
                                    $this->line("      🏠 Lote: " . ($unit['unitNumber'] ?? 'N/A') . " | Total: " . ($unit['total'] ?? 0));
                                }
                            }
                        }
                    }
                    $this->newLine();
                }

                if (count($salesData['data']) > 3) {
                    $this->line("... y " . (count($salesData['data']) - 3) . " ventas más");
                }

            } else {
                // Importación real
                $this->warn('⚠️  INICIANDO IMPORTACIÓN REAL...');
                
                if (!$this->confirm('¿Desea continuar con la importación?', true)) {
                    $this->info('Importación cancelada');
                    return 0;
                }

                $this->newLine();
                $this->info('🚀 Ejecutando importación...');

                $result = $importService->importSales($start, $end, $force);

                if ($result['success']) {
                    $this->newLine();
                    $this->info('✅ Importación completada exitosamente!');
                    $this->newLine();

                    if (!empty($result['data']['stats'])) {
                        $stats = $result['data']['stats'];
                        $this->table(['Métrica', 'Valor'], [
                            ['Clientes procesados', $stats['clients_processed'] ?? 0],
                            ['Clientes creados', $stats['clients_created'] ?? 0],
                            ['Contratos procesados', $stats['contracts_processed'] ?? 0],
                            ['Contratos creados', $stats['contracts_created'] ?? 0],
                            ['Errores', $stats['errors'] ?? 0]
                        ]);
                    }

                    if (!empty($result['data']['errors'])) {
                        $this->newLine();
                        $this->warn('⚠️  Errores encontrados:');
                        foreach ($result['data']['errors'] as $error) {
                            $this->error('  • ' . $error);
                        }
                    }

                } else {
                    $this->error('❌ Error en la importación: ' . ($result['message'] ?? 'Error desconocido'));
                    
                    if (!empty($result['data'])) {
                        $this->error('Data: ' . json_encode($result['data'], JSON_PRETTY_PRINT));
                    }
                    
                    if (!empty($result['data']['errors'])) {
                        foreach ($result['data']['errors'] as $error) {
                            $this->error('  • ' . $error);
                        }
                    }
                    return 1;
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
