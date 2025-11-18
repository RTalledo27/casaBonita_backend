<?php

namespace App\Console\Commands;

use App\Services\LogicwareContractImporter;
use Illuminate\Console\Command;
use Exception;

class LogicwareImportContractsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logicware:import-contracts
                            {--start-date= : Fecha de inicio (YYYY-MM-DD)}
                            {--end-date= : Fecha de fin (YYYY-MM-DD)}
                            {--force : Forzar actualización (consume del límite diario)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar contratos desde Logicware API';

    protected $importer;

    public function __construct(LogicwareContractImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $forceRefresh = $this->option('force');

        $this->info('🚀 Iniciando importación de contratos desde Logicware...');
        $this->newLine();

        if ($startDate) {
            $this->line("📅 Fecha inicio: {$startDate}");
        }

        if ($endDate) {
            $this->line("📅 Fecha fin: {$endDate}");
        }

        if ($forceRefresh) {
            $this->warn('⚠️  Modo FORCE: Consultará el API (consume límite diario)');
        } else {
            $this->info('💾 Usando datos en caché si están disponibles');
        }

        $this->newLine();

        try {
            $results = $this->importer->importContracts($startDate, $endDate, $forceRefresh);

            $this->newLine();
            $this->info('✅ Importación completada');
            $this->newLine();

            // Mostrar resumen
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total de ventas', $results['total_sales']],
                    ['Contratos creados', $results['contracts_created']],
                    ['Contratos omitidos', $results['contracts_skipped']],
                    ['Errores', count($results['errors'])]
                ]
            );

            // Mostrar advertencias
            if (!empty($results['warnings'])) {
                $this->newLine();
                $this->warn('⚠️  Advertencias:');
                foreach ($results['warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            // Mostrar errores
            if (!empty($results['errors'])) {
                $this->newLine();
                $this->error('❌ Errores:');
                foreach ($results['errors'] as $error) {
                    $this->line("  - Venta {$error['sale_id']}: {$error['error']}");
                }
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Error crítico: ' . $e->getMessage());
            $this->newLine();
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
