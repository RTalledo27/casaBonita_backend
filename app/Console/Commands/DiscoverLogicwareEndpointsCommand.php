<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LogicwareApiService;

class DiscoverLogicwareEndpointsCommand extends Command
{
    protected $signature = 'logicware:discover-endpoints';
    protected $description = 'Descubrir endpoints válidos del API de LOGICWARE CRM';

    public function handle()
    {
        $this->info('🔍 Descubriendo endpoints del API de LOGICWARE CRM...');
        $this->newLine();

        try {
            $service = app(LogicwareApiService::class);
            $results = $service->discoverEndpoints();

            $this->info('📊 Resultados:');
            $this->newLine();

            foreach ($results as $endpoint => $result) {
                if (isset($result['success']) && $result['success']) {
                    $this->line("<fg=green>✅ {$endpoint}</> - HTTP {$result['status']}");
                    if (!empty($result['body_preview'])) {
                        $this->line("   Vista previa: " . $result['body_preview']);
                    }
                } elseif (isset($result['status'])) {
                    $this->line("<fg=red>❌ {$endpoint}</> - HTTP {$result['status']}");
                } else {
                    $this->line("<fg=red>❌ {$endpoint}</> - Error: {$result['error']}");
                }
                $this->newLine();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
