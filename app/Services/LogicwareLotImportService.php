<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;
use Modules\Inventory\Models\LotFinancialTemplate;

/**
 * Servicio especializado para importación de lotes desde LogicWare API
 * 
 * Maneja el mapeo y creación de lotes con sus templates financieros
 */
class LogicwareLotImportService
{
    protected LogicwareApiService $logicwareApi;
    protected array $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    protected array $errors = [];
    protected array $warnings = [];

    public function __construct(LogicwareApiService $logicwareApi)
    {
        $this->logicwareApi = $logicwareApi;
    }

    /**
     * Importar lotes desde LogicWare por stage
     * 
     * @param string $projectCode Código del proyecto
     * @param string $stageId ID de la etapa
     * @param array $options Opciones de importación
     * @return array Resultado de la importación
     */
    public function importLotsByStage(
        string $projectCode,
        string $stageId,
        array $options = []
    ): array {
        DB::beginTransaction();
        
        try {
            // Obtener stock desde LogicWare
            $stockData = $this->logicwareApi->getStockByStage($projectCode, $stageId, $options['force_refresh'] ?? false);
            
            if (!isset($stockData['data']) || !is_array($stockData['data'])) {
                throw new Exception('Respuesta inválida del API de LogicWare');
            }

            $units = $stockData['data'];
            $this->stats['total'] = count($units);

            Log::info('[LogicwareLotImport] Iniciando importación de lotes', [
                'projectCode' => $projectCode,
                'stageId' => $stageId,
                'total_units' => $this->stats['total'],
                'options' => $options
            ]);

            // Procesar cada unidad
            foreach ($units as $index => $unit) {
                try {
                    $this->processUnit($unit, $options);
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->errors[] = [
                        'unit' => $unit['code'] ?? "Unidad #{$index}",
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('[LogicwareLotImport] Error procesando unidad', [
                        'unit_code' => $unit['code'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            $result = [
                'success' => true,
                'message' => $this->buildSuccessMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'projectCode' => $projectCode,
                'stageId' => $stageId,
                'is_mock' => $stockData['is_mock'] ?? false
            ];

            Log::info('[LogicwareLotImport] ✅ Importación completada', $result);

            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('[LogicwareLotImport] ❌ Error en importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error en la importación: ' . $e->getMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
        }
    }

    /**
     * Procesar una unidad individual
     * 
     * @param array $unit Datos de la unidad desde LogicWare
     * @param array $options Opciones de procesamiento
     * @return void
     * @throws Exception
     */
    protected function processUnit(array $unit, array $options): void
    {
        // Validar datos mínimos requeridos
        if (empty($unit['code'])) {
            throw new Exception('Unidad sin código identificador');
        }

        // Parsear código de unidad (formato: "A-01" -> Manzana: A, Lote: 01)
        $parsedCode = $this->parseUnitCode($unit['code']);
        
        if (!$parsedCode) {
            $this->warnings[] = [
                'unit' => $unit['code'],
                'warning' => 'Formato de código no reconocido, se intentará crear igual'
            ];
        }

        // Buscar o crear manzana
        $manzana = $this->findOrCreateManzana($parsedCode['manzana'] ?? 'X', $options);

        // Verificar si el lote ya existe
        $existingLot = Lot::where('num_lot', $parsedCode['lot_number'] ?? $unit['code'])
            ->where('manzana_id', $manzana->manzana_id)
            ->first();

        if ($existingLot) {
            if ($options['update_existing'] ?? false) {
                $this->updateLot($existingLot, $unit, $options);
                $this->stats['updated']++;
            } else {
                $this->stats['skipped']++;
                Log::info('[LogicwareLotImport] Lote existente omitido', [
                    'lot_number' => $existingLot->num_lot,
                    'manzana' => $manzana->name
                ]);
            }
        } else {
            $this->createLot($manzana, $unit, $parsedCode, $options);
            $this->stats['created']++;
        }
    }

    /**
     * Parsear código de unidad
     * Formatos soportados: "A-01", "MZ-A-01", "E2-02", etc.
     * 
     * @param string $code
     * @return array|null
     */
    protected function parseUnitCode(string $code): ?array
    {
        $code = trim(strtoupper($code));

        // Formato estándar: "A-01", "E-15"
        if (preg_match('/^([A-Z]+)-(\d+)$/', $code, $matches)) {
            return [
                'manzana' => $matches[1],
                'lot_number' => $matches[2]
            ];
        }

        // Formato con prefijo: "MZ-A-01", "MZA-01"
        if (preg_match('/^MZ[A-Z]?-?([A-Z]+)-(\d+)$/', $code, $matches)) {
            return [
                'manzana' => $matches[1],
                'lot_number' => $matches[2]
            ];
        }

        // Formato compuesto: "E2-02" -> Manzana: E2, Lote: 02
        if (preg_match('/^([A-Z]\d+)-(\d+)$/', $code, $matches)) {
            return [
                'manzana' => $matches[1],
                'lot_number' => $matches[2]
            ];
        }

        Log::warning('[LogicwareLotImport] No se pudo parsear código de unidad', [
            'code' => $code
        ]);

        return null;
    }

    /**
     * Buscar o crear manzana
     * 
     * @param string $manzanaName
     * @param array $options
     * @return Manzana
     * @throws Exception
     */
    protected function findOrCreateManzana(string $manzanaName, array $options): Manzana
    {
        $manzana = Manzana::where('name', $manzanaName)->first();

        if (!$manzana) {
            if ($options['create_manzanas'] ?? true) {
                // Obtener street_type por defecto (Avenida)
                $streetType = StreetType::where('name', 'Avenida')->first();
                
                if (!$streetType) {
                    // Crear street_type si no existe
                    $streetType = StreetType::create([
                        'name' => 'Avenida',
                        'abbreviation' => 'Av.'
                    ]);
                }

                $manzana = Manzana::create([
                    'name' => $manzanaName,
                    'street_type_id' => $streetType->street_type_id,
                    'address' => "Manzana {$manzanaName}",
                    'area_total' => 0, // Se calculará después
                    'number_lots' => 0 // Se calculará después
                ]);

                Log::info('[LogicwareLotImport] ✅ Manzana creada', [
                    'manzana_name' => $manzanaName,
                    'manzana_id' => $manzana->manzana_id
                ]);
            } else {
                throw new Exception("Manzana '{$manzanaName}' no existe y la creación automática está deshabilitada");
            }
        }

        return $manzana;
    }

    /**
     * Crear nuevo lote
     * 
     * @param Manzana $manzana
     * @param array $unit Datos de LogicWare
     * @param array|null $parsedCode
     * @param array $options
     * @return Lot
     */
    protected function createLot(Manzana $manzana, array $unit, ?array $parsedCode, array $options): Lot
    {
        $lotNumber = $parsedCode['lot_number'] ?? $unit['lotNumber'] ?? $unit['code'];

        // Obtener street_type_id por defecto (primer registro de la tabla)
        $defaultStreetTypeId = DB::table('street_types')->value('street_type_id') ?? 2;

        $lotData = [
            'manzana_id' => $manzana->manzana_id,
            'num_lot' => $lotNumber,
            'area_total' => $unit['area'] ?? 0,
            'area_m2' => $unit['area'] ?? 0, // Mismo valor que area_total
            'total_price' => $unit['price'] ?? 0, // Precio total del lote
            'currency' => $unit['currency'] ?? 'PEN', // Moneda
            'frontage' => $unit['frontage'] ?? null,
            'depth' => $unit['depth'] ?? null,
            'street_type_id' => $defaultStreetTypeId, // Tipo de vía por defecto
            'status' => $this->mapStatus($unit['status'] ?? 'disponible'),
            'external_id' => $unit['id'] ?? null,
            'external_code' => $unit['code'] ?? null,
            'observations' => $this->buildObservations($unit)
        ];

        $lot = Lot::create($lotData);

        Log::info('[LogicwareLotImport] ✅ Lote creado', [
            'lot_id' => $lot->lot_id,
            'num_lot' => $lot->num_lot,
            'manzana' => $manzana->name,
            'external_code' => $unit['code']
        ]);

        // Crear template financiero si se proporciona precio
        if (!empty($unit['price']) && ($options['create_templates'] ?? true)) {
            $this->createFinancialTemplate($lot, $unit);
        }

        return $lot;
    }

    /**
     * Actualizar lote existente
     * 
     * @param Lot $lot
     * @param array $unit
     * @param array $options
     * @return void
     */
    protected function updateLot(Lot $lot, array $unit, array $options): void
    {
        $updates = [];

        // Actualizar área si cambió
        if (isset($unit['area']) && $lot->area_total != $unit['area']) {
            $updates['area_total'] = $unit['area'];
        }

        // Actualizar dimensiones
        if (isset($unit['frontage']) && $lot->frontage != $unit['frontage']) {
            $updates['frontage'] = $unit['frontage'];
        }

        if (isset($unit['depth']) && $lot->depth != $unit['depth']) {
            $updates['depth'] = $unit['depth'];
        }

        // Actualizar status solo si la opción está habilitada
        if (($options['update_status'] ?? true) && isset($unit['status'])) {
            $newStatus = $this->mapStatus($unit['status']);
            if ($lot->status != $newStatus) {
                $updates['status'] = $newStatus;
            }
        }

        // Actualizar external_id si no estaba definido
        if (empty($lot->external_id) && !empty($unit['id'])) {
            $updates['external_id'] = $unit['id'];
        }

        // Actualizar external_code si no estaba definido
        if (empty($lot->external_code) && !empty($unit['code'])) {
            $updates['external_code'] = $unit['code'];
        }

        if (!empty($updates)) {
            $lot->update($updates);
            
            Log::info('[LogicwareLotImport] ✅ Lote actualizado', [
                'lot_id' => $lot->lot_id,
                'num_lot' => $lot->num_lot,
                'updates' => array_keys($updates)
            ]);
        }

        // Actualizar o crear template financiero
        if (!empty($unit['price']) && ($options['update_templates'] ?? true)) {
            $this->updateOrCreateFinancialTemplate($lot, $unit);
        }
    }

    /**
     * Crear template financiero para el lote
     * 
     * @param Lot $lot
     * @param array $unit
     * @return LotFinancialTemplate
     */
    protected function createFinancialTemplate(Lot $lot, array $unit): LotFinancialTemplate
    {
        $price = $unit['price'] ?? 0;
        
        // Calcular valores financieros por defecto
        $downPayment = round($price * 0.20, 2); // 20% de cuota inicial
        $financingAmount = $price - $downPayment;
        
        $templateData = [
            'lot_id' => $lot->lot_id,
            'precio_lista' => $price,
            'precio_venta' => $price,
            'descuento' => 0, // Sin descuento por defecto
            'precio_contado' => round($price * 0.95, 2), // 5% descuento por contado
            'cuota_inicial' => $downPayment,
            'ci_fraccionamiento' => $downPayment, // Mismo valor que cuota inicial
            'cuota_balon' => 0,
            'bono_bpp' => 0,
            
            // Calcular cuotas para diferentes plazos (sin interés)
            'installments_24' => $financingAmount > 0 ? round($financingAmount / 24, 2) : 0,
            'installments_40' => $financingAmount > 0 ? round($financingAmount / 40, 2) : 0,
            'installments_44' => $financingAmount > 0 ? round($financingAmount / 44, 2) : 0,
            'installments_55' => $financingAmount > 0 ? round($financingAmount / 55, 2) : 0,
            
            'currency' => $unit['currency'] ?? 'PEN',
            'is_active' => true,
            'imported_from_logicware' => true
        ];

        $template = LotFinancialTemplate::create($templateData);

        Log::info('[LogicwareLotImport] ✅ Template financiero creado', [
            'template_id' => $template->id,
            'lot_id' => $lot->lot_id,
            'precio_venta' => $price
        ]);

        return $template;
    }

    /**
     * Actualizar o crear template financiero
     * 
     * @param Lot $lot
     * @param array $unit
     * @return void
     */
    protected function updateOrCreateFinancialTemplate(Lot $lot, array $unit): void
    {
        $template = $lot->financialTemplate;

        if ($template) {
            // Actualizar solo si el precio cambió
            $newPrice = $unit['price'] ?? 0;
            
            if ($template->precio_venta != $newPrice) {
                $downPayment = round($newPrice * 0.20, 2);
                $financingAmount = $newPrice - $downPayment;

                $template->update([
                    'precio_lista' => $newPrice,
                    'precio_venta' => $newPrice,
                    'precio_contado' => round($newPrice * 0.95, 2),
                    'cuota_inicial' => $downPayment,
                    'installments_24' => $financingAmount > 0 ? round($financingAmount / 24, 2) : 0,
                    'installments_40' => $financingAmount > 0 ? round($financingAmount / 40, 2) : 0,
                    'installments_44' => $financingAmount > 0 ? round($financingAmount / 44, 2) : 0,
                    'installments_55' => $financingAmount > 0 ? round($financingAmount / 55, 2) : 0
                ]);

                Log::info('[LogicwareLotImport] ✅ Template financiero actualizado', [
                    'template_id' => $template->id,
                    'lot_id' => $lot->lot_id,
                    'new_price' => $newPrice
                ]);
            }
        } else {
            $this->createFinancialTemplate($lot, $unit);
        }
    }

    /**
     * Mapear status de LogicWare a status interno
     * 
     * @param string $logicwareStatus
     * @return string
     */
    protected function mapStatus(string $logicwareStatus): string
    {
        // Mapeo de estados de LogicWare a estados del sistema
        // Nota: "bloqueado" en LogicWare se trata como "reservado" en nuestro sistema
        $statusMap = [
            'disponible' => 'disponible',
            'available' => 'disponible',
            'vendido' => 'vendido',
            'sold' => 'vendido',
            'reservado' => 'reservado',
            'reserved' => 'reservado',
            'bloqueado' => 'reservado', // Bloqueado = Reservado en nuestro sistema
            'blocked' => 'reservado',
            'ocupado' => 'vendido',
            'occupied' => 'vendido'
        ];

        $normalized = strtolower(trim($logicwareStatus));
        $mapped = $statusMap[$normalized] ?? 'disponible';
        
        Log::info('[LogicwareLotImport] Status mapeado', [
            'original' => $logicwareStatus,
            'normalizado' => $normalized,
            'resultado' => $mapped
        ]);
        
        return $mapped;
    }

    /**
     * Construir observaciones del lote
     * 
     * @param array $unit
     * @return string|null
     */
    protected function buildObservations(array $unit): ?string
    {
        $observations = [];

        if (!empty($unit['description'])) {
            $observations[] = $unit['description'];
        }

        if (!empty($unit['features']) && is_array($unit['features'])) {
            $observations[] = 'Características: ' . implode(', ', $unit['features']);
        }

        if (!empty($unit['stageName'])) {
            $observations[] = 'Etapa: ' . $unit['stageName'];
        }

        return !empty($observations) ? implode(' | ', $observations) : null;
    }

    /**
     * Construir mensaje de éxito
     * 
     * @return string
     */
    protected function buildSuccessMessage(): string
    {
        $parts = [];

        if ($this->stats['created'] > 0) {
            $parts[] = "{$this->stats['created']} lotes creados";
        }

        if ($this->stats['updated'] > 0) {
            $parts[] = "{$this->stats['updated']} lotes actualizados";
        }

        if ($this->stats['skipped'] > 0) {
            $parts[] = "{$this->stats['skipped']} lotes omitidos";
        }

        if ($this->stats['errors'] > 0) {
            $parts[] = "{$this->stats['errors']} errores";
        }

        return !empty($parts) 
            ? 'Importación completada: ' . implode(', ', $parts)
            : 'No se procesaron lotes';
    }

    /**
     * Obtener estadísticas de la importación
     * 
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Obtener errores de la importación
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtener advertencias de la importación
     * 
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
