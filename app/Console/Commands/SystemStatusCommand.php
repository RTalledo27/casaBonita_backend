<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;

class SystemStatusCommand extends Command
{
    protected $signature = 'system:status {--json : Output as JSON}';
    protected $description = 'Muestra el estado completo del sistema: scheduler, listeners, eventos, caché';

    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('   🔍 ESTADO DEL SISTEMA - Casa Bonita API');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        $status = [
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'database' => $this->checkDatabase(),
            'scheduler' => $this->checkScheduler(),
            'events' => $this->checkEvents(),
            'cache' => $this->checkCache(),
            'logicware' => $this->checkLogicware(),
            'sales_cuts' => $this->checkSalesCuts(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->displayStatus($status);
        return 0;
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $this->info('✅ BASE DE DATOS: Conectada');
            
            $tables = [
                'contracts' => DB::table('contracts')->count(),
                'payments' => DB::table('payments')->count(),
                'sales_cuts' => DB::table('sales_cuts')->count(),
                'employees' => DB::table('employees')->count(),
            ];
            
            foreach ($tables as $table => $count) {
                $this->line("   📊 $table: $count registros");
            }
            
            return ['status' => 'connected', 'tables' => $tables];
        } catch (\Exception $e) {
            $this->error('❌ BASE DE DATOS: Error - ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkScheduler(): array
    {
        $this->newLine();
        $this->info('⏰ SCHEDULER (Tareas Programadas):');
        
        // Verificar última ejecución en logs
        $logFile = storage_path('logs/laravel.log');
        $schedulerLogs = [];
        
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            $lines = explode("\n", $logs);
            $recentLines = array_slice($lines, -500); // Últimas 500 líneas
            
            $patterns = [
                'Token Renewal' => '/\[LogicwareScheduler\].*renovado/',
                'Daily Cut' => '/\[SalesCut\].*Corte diario creado/',
                'Bonus Calculation' => '/\[BonusCalculator\]/',
                'Contract Import' => '/\[LogicwareImport\]/',
                'Schedule Generation' => '/\[ScheduleGenerator\]/',
            ];
            
            foreach ($patterns as $name => $pattern) {
                $matches = preg_grep($pattern, $recentLines);
                $count = count($matches);
                
                if ($count > 0) {
                    $lastMatch = end($matches);
                    preg_match('/\[(.*?)\]/', $lastMatch, $dateMatch);
                    $date = $dateMatch[1] ?? 'unknown';
                    
                    $this->line("   ✅ $name: $count eventos (último: $date)");
                    $schedulerLogs[$name] = ['count' => $count, 'last' => $date];
                } else {
                    $this->warn("   ⚠️  $name: Sin actividad reciente");
                    $schedulerLogs[$name] = ['count' => 0, 'last' => null];
                }
            }
        } else {
            $this->warn('   ⚠️  No se encontró el archivo de logs');
        }
        
        return ['logs' => $schedulerLogs];
    }

    private function checkEvents(): array
    {
        $this->newLine();
        $this->info('🎯 EVENTOS Y LISTENERS:');
        
        $events = [
            'ContractCreated' => 'UpdateTodaySalesCut@handleContractCreated',
            'PaymentRecorded' => 'UpdateTodaySalesCut@handlePaymentRecorded',
        ];
        
        foreach ($events as $event => $listener) {
            $eventClass = "App\\Events\\$event";
            $listenerClass = explode('@', $listener)[0];
            
            $eventExists = class_exists($eventClass);
            $listenerExists = class_exists("App\\Listeners\\$listenerClass");
            
            if ($eventExists && $listenerExists) {
                $this->line("   ✅ $event → $listener");
            } else {
                $this->error("   ❌ $event → $listener (falta clase)");
            }
        }
        
        // Verificar eventos recientes en logs
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            $lines = explode("\n", $logs);
            $recentLines = array_slice($lines, -200);
            
            $eventCount = count(preg_grep('/\[SalesCut\] (Venta|Pago) agregada?/', $recentLines));
            $this->line("   📊 Eventos procesados recientemente: $eventCount");
        }
        
        return ['events' => $events, 'status' => 'registered'];
    }

    private function checkCache(): array
    {
        $this->newLine();
        $this->info('💾 SISTEMA DE CACHÉ:');
        
        try {
            // Test cache
            Cache::put('system_status_test', true, 10);
            $test = Cache::get('system_status_test');
            
            if ($test) {
                $this->line('   ✅ Caché funcionando correctamente');
            } else {
                $this->warn('   ⚠️  Caché no responde');
            }
            
            // Verificar claves importantes
            $cacheKeys = [
                'logicware_token' => Cache::has('logicware_token'),
                'logicware_stock_cache' => Cache::has('logicware_stock_cache'),
            ];
            
            foreach ($cacheKeys as $key => $exists) {
                $status = $exists ? '✅' : '❌';
                $this->line("   $status $key: " . ($exists ? 'presente' : 'ausente'));
            }
            
            return ['status' => 'working', 'keys' => $cacheKeys];
        } catch (\Exception $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkLogicware(): array
    {
        $this->newLine();
        $this->info('🔗 INTEGRACIÓN LOGICWARE:');
        
        try {
            // Verificar token en caché
            $hasToken = Cache::has('logicware_token');
            $this->line('   ' . ($hasToken ? '✅' : '❌') . ' Token en caché: ' . ($hasToken ? 'presente' : 'ausente'));
            
            if ($hasToken) {
                $tokenData = Cache::get('logicware_token');
                if (is_array($tokenData) && isset($tokenData['expires_at'])) {
                    $expiresAt = Carbon::parse($tokenData['expires_at']);
                    $this->line("   ⏰ Token expira: {$expiresAt->format('Y-m-d H:i:s')} ({$expiresAt->diffForHumans()})");
                }
            }
            
            // Verificar requests diarios
            $requestCount = Cache::get('logicware_daily_requests', 0);
            $this->line("   📊 Requests hoy: $requestCount/4");
            
            // Verificar último stock cacheado
            if (Cache::has('logicware_stock_cache')) {
                $stockData = Cache::get('logicware_stock_cache');
                if (isset($stockData['cached_at'])) {
                    $cachedAt = Carbon::parse($stockData['cached_at']);
                    $this->line("   💾 Último stock: {$cachedAt->format('Y-m-d H:i:s')} ({$cachedAt->diffForHumans()})");
                    $this->line("   📦 Unidades en caché: " . (count($stockData['data'] ?? [])));
                }
            }
            
            return [
                'has_token' => $hasToken,
                'daily_requests' => $requestCount,
                'has_cache' => Cache::has('logicware_stock_cache'),
            ];
        } catch (\Exception $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkSalesCuts(): array
    {
        $this->newLine();
        $this->info('💰 CORTES DE VENTA:');
        
        try {
            // Corte de hoy
            $today = Carbon::today();
            $todayCut = DB::table('sales_cuts')
                ->whereDate('cut_date', $today)
                ->first();
            
            if ($todayCut) {
                $this->line("   ✅ Corte de hoy: {$todayCut->cut_date}");
                $this->line("   📊 Total ventas: {$todayCut->total_sales}");
                $this->line("   💵 Monto total: S/ " . number_format($todayCut->total_amount, 2));
                $this->line("   📝 Última actualización: {$todayCut->updated_at}");
            } else {
                $this->warn('   ⚠️  No existe corte para hoy');
            }
            
            // Últimos 7 días
            $lastWeek = DB::table('sales_cuts')
                ->where('cut_date', '>=', Carbon::now()->subDays(7))
                ->orderBy('cut_date', 'desc')
                ->get();
            
            $this->line("   📊 Cortes últimos 7 días: " . $lastWeek->count());
            
            return [
                'today' => $todayCut ? [
                    'date' => $todayCut->cut_date,
                    'sales' => $todayCut->total_sales,
                    'amount' => $todayCut->total_amount,
                ] : null,
                'last_week_count' => $lastWeek->count(),
            ];
        } catch (\Exception $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function displayStatus(array $status): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('   📋 RESUMEN DEL SISTEMA');
        $this->info('═══════════════════════════════════════════════════════════════');
        
        $components = [
            'Base de Datos' => $status['database']['status'] === 'connected',
            'Scheduler' => !empty($status['scheduler']['logs']),
            'Eventos' => $status['events']['status'] === 'registered',
            'Caché' => $status['cache']['status'] === 'working',
            'Logicware' => $status['logicware']['has_token'] ?? false,
            'Cortes de Venta' => $status['sales_cuts']['today'] !== null,
        ];
        
        $allOk = true;
        foreach ($components as $name => $ok) {
            $icon = $ok ? '✅' : '❌';
            $this->line("   $icon $name");
            if (!$ok) $allOk = false;
        }
        
        $this->newLine();
        if ($allOk) {
            $this->info('🎉 TODOS LOS SISTEMAS OPERATIVOS');
        } else {
            $this->warn('⚠️  ALGUNOS SISTEMAS REQUIEREN ATENCIÓN');
        }
        $this->info('═══════════════════════════════════════════════════════════════');
    }
}
