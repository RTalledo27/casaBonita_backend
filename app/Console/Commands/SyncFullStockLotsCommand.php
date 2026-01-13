<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Services\ExternalLotImportService;

class SyncFullStockLotsCommand extends Command
{
    protected $signature = 'lots:sync-full-stock
                            {--force_refresh : Forzar consulta real al API (consume cuota diaria)}
                            {--force : Ejecutar sin confirmación}';

    protected $description = 'Sincronizar TODOS los lotes usando el full stock de Logicware';

    public function handle(): int
    {
        $forceRefresh = (bool) $this->option('force_refresh');

        if (!$this->option('force')) {
            if (!$this->confirm('¿Desea sincronizar todos los lotes desde full stock?', true)) {
                $this->warn('Operación cancelada');
                return Command::SUCCESS;
            }
        }

        $service = app(ExternalLotImportService::class);
        $result = $service->importLotsFromFullStock($forceRefresh);

        if (!($result['success'] ?? false)) {
            $this->error('Sincronización completada con errores');
            return Command::FAILURE;
        }

        $this->info('Sincronización completada exitosamente');
        return Command::SUCCESS;
    }
}

