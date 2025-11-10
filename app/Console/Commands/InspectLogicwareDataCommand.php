<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\Log;

/**
 * Comando para inspeccionar la estructura de datos del API de LOGICWARE
 */
class InspectLogicwareDataCommand extends Command
{
    protected $signature = 'logicware:inspect-data {--limit=5 : Número de registros a mostrar}';
    protected $description = 'Inspeccionar la estructura de datos que viene del API de LOGICWARE';

    public function handle(): int
    {
        $this->info('🔍 Inspeccionando datos del API de LOGICWARE CRM...');
        $this->newLine();

        try {
            $apiService = app(LogicwareApiService::class);
            
            // Obtener datos (usa caché si está disponible)
            $limit = (int) $this->option('limit');
            $this->info("⏳ Obteniendo primeros {$limit} registros...");
            
            $response = $apiService->getProperties();
            
            if (!isset($response['data']) || empty($response['data'])) {
                $this->error('❌ No se recibieron datos del API');
                return Command::FAILURE;
            }

            $total = count($response['data']);
            $this->info("✅ Total de registros disponibles: {$total}");
            $this->newLine();

            // Mostrar metadata de caché
            if (isset($response['cached_at'])) {
                $this->line("📦 Datos desde CACHÉ");
                $this->line("   Cached at: {$response['cached_at']}");
                $this->line("   Expires at: {$response['cache_expires_at']}");
                $this->newLine();
            }

            // Analizar estructura del primer registro
            $firstRecord = $response['data'][0];
            $this->info('📋 Estructura del primer registro:');
            $this->newLine();
            
            $this->table(
                ['Campo', 'Tipo', 'Valor'],
                collect($firstRecord)->map(function ($value, $key) {
                    return [
                        $key,
                        gettype($value),
                        is_array($value) ? json_encode($value) : (string) $value
                    ];
                })->values()->toArray()
            );

            $this->newLine();
            $this->info("📊 Mostrando primeros {$limit} registros:");
            $this->newLine();

            // Mostrar registros de muestra
            foreach (array_slice($response['data'], 0, $limit) as $index => $property) {
                $this->line("Registro #" . ($index + 1));
                $this->line("  Código: " . ($property['code'] ?? 'N/A'));
                $this->line("  Estado: " . ($property['status'] ?? 'N/A'));
                $this->line("  Área: " . ($property['area'] ?? 'N/A'));
                $this->line("  Precio: " . ($property['price'] ?? 'N/A'));
                $this->line("  Moneda: " . ($property['currency'] ?? 'N/A'));
                
                // Mostrar todos los campos disponibles
                $allFields = array_keys($property);
                $this->line("  Campos disponibles: " . implode(', ', $allFields));
                $this->newLine();
            }

            // Análisis de códigos
            $this->info('🔍 Análisis de formatos de códigos:');
            $codes = collect($response['data'])->pluck('code')->filter()->take(20);
            
            $patterns = [
                'letra-numero' => 0,
                'letranumero-numero' => 0,
                'otros' => 0
            ];

            foreach ($codes as $code) {
                if (preg_match('/^([A-Z]+)-(\d+)$/i', $code)) {
                    $patterns['letra-numero']++;
                } elseif (preg_match('/^([A-Z]+\d+)-(\d+)$/i', $code)) {
                    $patterns['letranumero-numero']++;
                } else {
                    $patterns['otros']++;
                }
            }

            $this->table(
                ['Patrón', 'Cantidad'],
                collect($patterns)->map(fn($count, $pattern) => [$pattern, $count])->values()->toArray()
            );

            $this->newLine();
            $this->info('✅ Inspección completada');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('[InspectLogicwareData] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
