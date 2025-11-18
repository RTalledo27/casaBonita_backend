<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\Cache;
use Exception;

class RenewLogicwareToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logicware:renew-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renovar automáticamente el Bearer Token de Logicware';

    protected LogicwareApiService $logicwareService;

    public function __construct(LogicwareApiService $logicwareService)
    {
        parent::__construct();
        $this->logicwareService = $logicwareService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $subdomain = config('services.logicware.subdomain', 'casabonita');
            $cacheKey = "logicware_bearer_token_{$subdomain}";
            
            $this->info('🔄 Renovando Bearer Token de Logicware...');
            
            // SIEMPRE forzar renovación para asegurar token válido
            // El caché de 23h es para evitar renovaciones excesivas entre ejecuciones del scheduler
            $token = $this->logicwareService->generateToken(true); // Force refresh
            
            $this->info('✅ Token renovado exitosamente');
            $this->line('📝 Token: ' . substr($token, 0, 50) . '...');
            $this->line('⏰ Válido hasta: ' . now()->addHours(23)->format('Y-m-d H:i:s'));
            $this->line('💾 Guardado en caché automáticamente');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('❌ Error al renovar token: ' . $e->getMessage());
            \Log::error('[RenewToken] Error en comando', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
