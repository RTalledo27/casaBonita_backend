<?php

namespace Modules\Inventory\Services;

use App\Services\LogicwareApiService;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Modules\Inventory\Models\StreetType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para importar lotes desde API externa de LOGICWARE CRM
 * 
 * Este servicio transforma los datos del API externa al formato de nuestra base de datos.
 * Maneja la lógica de parsing del código de lote (Ej: "E2-02" -> Manzana: E2, Lote: 02)
 */
class ExternalLotImportService
{
    protected LogicwareApiService $apiService;
    /** @var array<string, array{status: string, resolved_by_lock: bool}>|null Cache: unitNumber => status + si es resuelto por lock (disponible + lock "por resolucion"/Bloqueo Comercial) */
    protected ?array $fullStockByUnitNumber = null;
    protected bool $currentForceRefresh = false;
    protected array $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    protected array $errors = [];

    public function __construct(LogicwareApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    protected function normalizeUnitNumber(?string $unitNumber): ?string
    {
        if (!$unitNumber) {
            return null;
        }

        $raw = strtoupper(trim($unitNumber));
        if ($raw === '') {
            return null;
        }

        $raw = str_replace([' ', '_'], ['', '-'], $raw);
        $raw = preg_replace('/-+/', '-', $raw);

        $parts = explode('-', $raw, 2);
        if (count($parts) !== 2) {
            return $raw;
        }

        $block = trim($parts[0]);
        $lotPart = trim($parts[1]);
        if ($block === '' || $lotPart === '') {
            return $raw;
        }

        if (ctype_digit($lotPart)) {
            $lotPart = (string) ((int) $lotPart);
        }

        return $block . '-' . $lotPart;
    }

    /**
     * Carga el full stock usando la MISMA fuente que el modal "Stock completo" (getFullStockData),
     * para reutilizar la caché logicware_full_stock_{subdomain} y tener lock_type/lock_description.
     * Cruce: mismo lote (code/unitNumber) + disponible + lock "por resolución"/"Bloqueo Comercial" → resuelto.
     */
    protected function ensureFullStockLoaded(bool $forceRefresh = false): void
    {
        if ($this->fullStockByUnitNumber !== null) {
            return;
        }

        try {
            // Usar getFullStockData() = misma caché que Ventas → Stock completo (no getProperties)
            $res = $this->apiService->getFullStockData($forceRefresh);
            // Soportar varias estructuras: { data: [] }, { data: { data: [] } }, { data: { units: [] } }
            $units = $res['data'] ?? [];
            if (is_array($units) && isset($units['data'])) {
                $units = $units['data'];
            } elseif (is_array($units) && isset($units['units'])) {
                $units = $units['units'];
            } elseif (!is_array($units)) {
                $units = [];
            }

            $index = [];
            $resolvedCount = 0;
            $firstUnitKeys = null;
            if (is_array($units)) {
                foreach ($units as $u) {
                    if ($firstUnitKeys === null && is_array($u)) {
                        $firstUnitKeys = array_keys($u);
                    }
                    $unitNumber = $this->normalizeUnitNumber((string) ($u['unitNumber'] ?? $u['code'] ?? $u['unit_number'] ?? ''));
                    if (!$unitNumber) {
                        continue;
                    }

                    $status = strtolower(trim((string) ($u['status'] ?? '')));
                    if (in_array($status, ['available', 'libre'], true)) {
                        $status = 'disponible';
                    }
                    $lockType = (string) ($u['lockType'] ?? $u['lock_type'] ?? '');
                    $lockDesc = (string) ($u['lockDescription'] ?? $u['lock_description'] ?? '');
                    // Lote disponible con bloqueo "por resolución" o "Bloqueo Comercial" = contrato resuelto en Logicware
                    $resolvedByLock = ($status === 'disponible'
                        && (stripos($lockType, 'Bloqueo Comercial') !== false || stripos($lockDesc, 'por resolucion') !== false));
                    if ($resolvedByLock) {
                        $resolvedCount++;
                    }

                    $index[$unitNumber] = ['status' => $status, 'resolved_by_lock' => $resolvedByLock];
                }
            }

            $this->fullStockByUnitNumber = $index;
            Log::info('[ExternalLotImport] Full stock cargado (misma fuente que Stock completo)', [
                'units' => count($index),
                'resolved_by_lock_count' => $resolvedCount,
                'first_unit_keys_sample' => $firstUnitKeys,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ExternalLotImport] No se pudo cargar full stock (se continuará sin estado)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->fullStockByUnitNumber = [];
        }
    }

    protected function getUnitStatusFromFullStock(?string $unitNumber, bool $forceRefresh = false): ?string
    {
        $this->ensureFullStockLoaded($forceRefresh);
        $key = $this->normalizeUnitNumber($unitNumber);
        if (!$key || !isset($this->fullStockByUnitNumber[$key])) {
            return null;
        }
        $entry = $this->fullStockByUnitNumber[$key];
        return is_array($entry) ? ($entry['status'] ?? null) : (string) $entry;
    }

    /**
     * Indica si en el full stock de Logicware el lote está disponible con lock "por resolución" o "Bloqueo Comercial"
     * (contrato resuelto en Logicware). Se usa para marcar el contrato como resuelto al importar si no viene en el documento.
     */
    protected function getUnitIsResolvedByLockFromFullStock(?string $unitNumber, bool $forceRefresh = false): bool
    {
        $this->ensureFullStockLoaded($forceRefresh);
        $key = $this->normalizeUnitNumber($unitNumber);
        if (!$key || !isset($this->fullStockByUnitNumber[$key])) {
            return false;
        }
        $entry = $this->fullStockByUnitNumber[$key];
        return is_array($entry) && !empty($entry['resolved_by_lock']);
    }

    public function importSalesWithProgress(\App\Models\AsyncImportProcess $importProcess, ?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false): array
    {
        $this->resetStats();
        $this->fullStockByUnitNumber = null;
        $this->currentForceRefresh = $forceRefresh;

        try {
            Log::info('[ExternalLotImport] Iniciando importación de ventas con progreso', [
                'process_id' => $importProcess->id,
                'start' => $startDate,
                'end' => $endDate,
            ]);

            $salesResponse = $this->apiService->getSales($startDate, $endDate, $forceRefresh);
            if (!isset($salesResponse['data']) || !is_array($salesResponse['data'])) {
                throw new Exception('Formato de respuesta inesperado del API para ventas');
            }

            $clients = $salesResponse['data'];
            $totalClients = count($clients);
            $totalDocuments = 0;
            foreach ($clients as $c) {
                if (!empty($c['documents']) && is_array($c['documents'])) {
                    $totalDocuments += count($c['documents']);
                }
            }

            $this->stats['total'] = $totalDocuments;

            $importProcess->update([
                'total_rows' => $totalDocuments,
                'processed_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
                'progress_percentage' => 0,
                'summary' => array_merge($importProcess->summary ?? [], [
                    'total_clients' => $totalClients,
                    'total_documents' => $totalDocuments,
                ]),
            ]);

            // Cargar full stock UNA VEZ al inicio (misma fuente que Stock completo) para cruce resuelto por lock
            $this->ensureFullStockLoaded($forceRefresh);

            $processed = 0;
            $successful = 0;
            $failed = 0;

            foreach ($clients as $clientDoc) {
                $client = $this->upsertClientFromSaleDoc($clientDoc);

                if (!empty($clientDoc['documents']) && is_array($clientDoc['documents'])) {
                    foreach ($clientDoc['documents'] as $document) {
                        $ok = $this->processSaleDocumentItem($client, $document);

                        $processed++;
                        if ($ok) $successful++;
                        else $failed++;

                        $importProcess->updateProgress($processed, $successful, $failed);
                    }
                }
            }

            return [
                'success' => $failed === 0,
                'message' => 'Importación completada',
                'data' => [
                    'stats' => array_merge($this->stats, [
                        'total_clients' => $totalClients,
                        'total_documents' => $totalDocuments,
                        'processed_documents' => $processed,
                        'successful_documents' => $successful,
                        'failed_documents' => $failed,
                    ]),
                    'errors' => $this->errors,
                ],
            ];

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error importando ventas con progreso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'errors' => [$e->getMessage()],
                ],
            ];
        }
    }

    /**
     * Importar todos los lotes disponibles desde el API externa
     * 
     * @param array $options Opciones de importación
     * @return array Estadísticas de la importación
     */
    public function importLots(array $options = []): array
    {
        $this->resetStats();
        $forceRefresh = (bool)($options['force_refresh'] ?? false);
        
        try {
            Log::info('[ExternalLotImport] Iniciando importación de lotes externos');

            // Obtener propiedades del API (FULL STOCK con caché)
            $properties = $this->apiService->getProperties([], $forceRefresh);
            $units = $properties['data']['data'] ?? $properties['data'] ?? [];
            
            if (!is_array($units)) {
                throw new Exception('Formato de respuesta inesperado del API');
            }

            $this->stats['total'] = count($units);
            
            Log::info('[ExternalLotImport] Propiedades obtenidas', [
                'total' => $this->stats['total']
            ]);

            DB::beginTransaction();
            
            try {
                foreach ($units as $property) {
                    $this->processProperty($property, $options);
                }
                
                DB::commit();
                
                Log::info('[ExternalLotImport] Importación completada exitosamente', $this->stats);
                
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error en importación', [
                'error' => $e->getMessage()
            ]);
            
            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
        }

        return [
            'success' => $this->stats['errors'] === 0,
            'stats' => $this->stats,
            'errors' => $this->errors
        ];
    }

    /**
     * Importar lotes desde el mismo caché de Stock completo (getFullStockData).
     * Así se reutiliza logicware_full_stock_{subdomain} y se obtiene model_name → Tipo de calle.
     */
    public function importLotsFromFullStock(bool $forceRefresh = false, array $options = []): array
    {
        $this->resetStats();

        try {
            Log::info('[ExternalLotImport] Iniciando importación de lotes desde FULL STOCK (misma fuente que Stock completo)', [
                'force_refresh' => $forceRefresh
            ]);

            $res = $this->apiService->getFullStockData($forceRefresh);
            $units = $res['data'] ?? [];
            if (is_array($units) && isset($units['data'])) {
                $units = $units['data'];
            } elseif (is_array($units) && isset($units['units'])) {
                $units = $units['units'];
            } elseif (!is_array($units)) {
                $units = [];
            }

            if (empty($units)) {
                throw new Exception('Formato de respuesta inesperado del API (full stock) o sin unidades');
            }

            $this->stats['total'] = count($units);
            Log::info('[ExternalLotImport] Unidades obtenidas del caché full stock', ['total' => count($units)]);

            DB::beginTransaction();
            try {
                foreach ($units as $property) {
                    if (is_array($property)) {
                        $this->processProperty($property, $options);
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error en importación FULL STOCK', [
                'error' => $e->getMessage()
            ]);

            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
        }

        return [
            'success' => $this->stats['errors'] === 0,
            'stats' => $this->stats,
            'errors' => $this->errors
        ];
    }

    /**
     * Procesar una propiedad individual del API
     * 
     * @param array $property Datos de la propiedad
     * @param array $options Opciones de procesamiento
     */
    protected function processProperty(array $property, array $options = []): void
    {
        try {
            // Extraer y validar el código (Ej: "E2-02")
            $code = $property['code'] ?? $property['unitNumber'] ?? $property['unit_number'] ?? null;
            
            if (!$code) {
                Log::warning('[ExternalLotImport] Propiedad sin código', [
                    'property_id' => $property['id'] ?? 'N/A'
                ]);
                $this->stats['skipped']++;
                return;
            }

            // Parsear el código para obtener manzana y lote
            $parsed = $this->parsePropertyCode($code);
            
            if (!$parsed) {
                Log::warning('[ExternalLotImport] Código de propiedad inválido', [
                    'code' => $code
                ]);
                $this->stats['skipped']++;
                $this->errors[] = "Código inválido: {$code}";
                return;
            }

            // Obtener o crear la manzana
            $manzana = $this->getOrCreateManzana($parsed['manzana']);
            
            // Transformar los datos de la propiedad a nuestro formato
            $lotData = $this->transformPropertyToLot($property, $parsed, $manzana);
            
            // Crear o actualizar el lote
            $lot = $this->createOrUpdateLot($lotData, $manzana);
            
            // Crear o actualizar template financiero si hay datos disponibles
            if (isset($property['financial_data'])) {
                $this->createOrUpdateFinancialTemplate($lot, $property['financial_data']);
            }

            Log::info('[ExternalLotImport] Lote procesado', [
                'code' => $code,
                'manzana' => $parsed['manzana'],
                'lote' => $parsed['lote'],
                'lot_id' => $lot->lot_id
            ]);

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error procesando propiedad', [
                'code' => $code ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            
            $this->stats['errors']++;
            $this->errors[] = "Error en {$code}: {$e->getMessage()}";
        }
    }

    /**
     * Parsear el código de propiedad (Ej: "E2-02" -> ['manzana' => 'E2', 'lote' => '02'])
     * 
     * Formatos soportados:
     * - "E2-02" -> Manzana: E2, Lote: 02
     * - "A-15" -> Manzana: A, Lote: 15
     * - "MZ-A-LT-05" -> Manzana: A, Lote: 05 (formato alternativo)
     * 
     * @param string $code
     * @return array|null
     */
    protected function parsePropertyCode(string $code): ?array
    {
        $code = trim($code);
        
        // Formato principal: "E2-02" o "A-15"
        if (preg_match('/^([A-Z]+\d*)-(\d+)$/i', $code, $matches)) {
            return [
                'manzana' => strtoupper($matches[1]),
                'lote' => $matches[2]
            ];
        }
        
        // Formato alternativo: "MZ-A-LT-05" o similar
        if (preg_match('/^MZ[.-]?([A-Z]+\d*)[.-]?LT[.-]?(\d+)$/i', $code, $matches)) {
            return [
                'manzana' => strtoupper($matches[1]),
                'lote' => $matches[2]
            ];
        }
        
        // Formato con guión bajo: "E2_02"
        if (preg_match('/^([A-Z]+\d*)_(\d+)$/i', $code, $matches)) {
            return [
                'manzana' => strtoupper($matches[1]),
                'lote' => $matches[2]
            ];
        }
        
        Log::warning('[ExternalLotImport] Formato de código no reconocido', [
            'code' => $code
        ]);
        
        return null;
    }

    /**
     * Obtener o crear una manzana
     * 
     * @param string $manzanaName
     * @return Manzana
     */
    protected function getOrCreateManzana(string $manzanaName): Manzana
    {
        $manzana = Manzana::where('name', $manzanaName)->first();
        
        if (!$manzana) {
            $manzana = Manzana::create([
                'name' => $manzanaName
            ]);
            
            Log::info('[ExternalLotImport] Nueva manzana creada', [
                'name' => $manzanaName,
                'manzana_id' => $manzana->manzana_id
            ]);
        }
        
        return $manzana;
    }

    /**
     * Transformar datos de la propiedad externa a formato de lote interno
     * 
     * @param array $property Datos de la propiedad externa
     * @param array $parsed Datos parseados (manzana y lote)
     * @param Manzana $manzana Instancia de manzana
     * @return array
     */
    protected function transformPropertyToLot(array $property, array $parsed, Manzana $manzana): array
    {
        $externalStatusRaw = (string) ($property['status'] ?? $property['state'] ?? 'disponible');
        $externalStatus = strtolower(trim($externalStatusRaw));

        $status = match (true) {
            in_array($externalStatus, ['vendido', 'sold', 'sale'], true) => 'vendido',
            in_array($externalStatus, ['reservado', 'reserved', 'bloqueado', 'blocked'], true) => 'reservado',
            default => 'disponible',
        };

        // Extraer precios según estructura Logicware / full stock (cache: price, price_per_sqm; API: basePrice, unitPrice)
        $basePrice = $this->parseNumericValue($property['basePrice'] ?? $property['base_price'] ?? $property['price'] ?? 0);
        $unitPrice = $this->parseNumericValue($property['unitPrice'] ?? $property['unit_price'] ?? $property['price'] ?? 0);
        $discount = $this->parseNumericValue($property['discount'] ?? 0);
        
        // 🔥 CORRECCIÓN: total_price = unitPrice - discount (igual que en contratos)
        $totalPrice = $unitPrice - $discount;
        
        $areaM2 = $this->parseNumericValue($property['area'] ?? $property['areaSqm'] ?? $property['area_sqm'] ?? 0);
        
        return [
            'manzana_id' => $manzana->manzana_id,
            'num_lot' => (int) $parsed['lote'],
            'area_m2' => $areaM2,
            'area_construction_m2' => $this->parseNumericValue($property['construction_area'] ?? null),
            'total_price' => $totalPrice, // 🔥 Precio final = unitPrice - descuento
            'currency' => strtoupper($property['currency'] ?? 'PEN'),
            'status' => $status,
            'street_type_id' => $this->resolveStreetTypeId($property),
            
            // Campos de sincronización con API externa
            'external_id' => $property['id'] ?? null,
            'external_code' => $property['code'] ?? null,
            'external_sync_at' => now(),
            'external_data' => [
                'name' => $property['name'] ?? null,
                'block' => $property['block'] ?? null,
                'project' => $property['project'] ?? null,
                'model_name' => $property['model_name'] ?? $property['modelName'] ?? null, // Tipo de calle en full stock
                'base_price' => $basePrice,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'raw_data' => $property
            ]
        ];
    }

    /**
     * Crear o actualizar un lote
     * 
     * @param array $lotData
     * @param Manzana $manzana
     * @return Lot
     */
    protected function createOrUpdateLot(array $lotData, Manzana $manzana): Lot
    {
        $lot = Lot::where('num_lot', $lotData['num_lot'])
                  ->where('manzana_id', $manzana->manzana_id)
                  ->first();

        if ($lot) {
            $defaultStreetTypeId = $this->getDefaultStreetTypeId();
            if (
                isset($lotData['street_type_id']) &&
                (int) $lotData['street_type_id'] === (int) $defaultStreetTypeId &&
                !empty($lot->street_type_id) &&
                (int) $lot->street_type_id !== (int) $defaultStreetTypeId
            ) {
                unset($lotData['street_type_id']);
            }

            if (isset($lotData['status'])) {
                $incoming = strtolower(trim((string) $lotData['status']));
                $current = strtolower(trim((string) $lot->status));

                if ($current === 'vendido') {
                    $lotData['status'] = 'vendido';
                } elseif ($current === 'reservado' && $incoming === 'disponible') {
                    $lotData['status'] = 'reservado';
                }
            }

            // Actualizar lote existente
            $lot->update($lotData);
            $this->stats['updated']++;
            
            Log::info('[ExternalLotImport] Lote actualizado', [
                'lot_id' => $lot->lot_id,
                'num_lot' => $lot->num_lot,
                'manzana' => $manzana->name
            ]);
        } else {
            // Crear nuevo lote
            $lot = Lot::create($lotData);
            $this->stats['created']++;
            
            Log::info('[ExternalLotImport] Nuevo lote creado', [
                'lot_id' => $lot->lot_id,
                'num_lot' => $lot->num_lot,
                'manzana' => $manzana->name
            ]);
        }

        return $lot;
    }

    /**
     * Crear o actualizar template financiero para un lote
     * 
     * @param Lot $lot
     * @param array $financialData
     */
    protected function createOrUpdateFinancialTemplate(Lot $lot, array $financialData): void
    {
        $templateData = [
            'lot_id' => $lot->lot_id,
            'precio_lista' => $this->parseNumericValue($financialData['list_price'] ?? $lot->total_price),
            'descuento' => $this->parseNumericValue($financialData['discount'] ?? 0),
            'precio_venta' => $this->parseNumericValue($financialData['sale_price'] ?? $lot->total_price),
            'precio_contado' => $this->parseNumericValue($financialData['cash_price'] ?? null),
            'cuota_balon' => $this->parseNumericValue($financialData['balloon_payment'] ?? 0),
            'bono_bpp' => $this->parseNumericValue($financialData['bpp_bonus'] ?? 0),
            'cuota_inicial' => $this->parseNumericValue($financialData['down_payment'] ?? 0),
            'ci_fraccionamiento' => $this->parseNumericValue($financialData['subdivision_payment'] ?? 0),
            // Cuotas por plazo
            'installments_12' => $this->parseNumericValue($financialData['installments_12'] ?? 0),
            'installments_24' => $this->parseNumericValue($financialData['installments_24'] ?? 0),
            'installments_36' => $this->parseNumericValue($financialData['installments_36'] ?? 0),
            'installments_40' => $this->parseNumericValue($financialData['installments_40'] ?? 0),
            'installments_44' => $this->parseNumericValue($financialData['installments_44'] ?? 0),
            'installments_48' => $this->parseNumericValue($financialData['installments_48'] ?? 0),
            'installments_55' => $this->parseNumericValue($financialData['installments_55'] ?? 0),
            'installments_60' => $this->parseNumericValue($financialData['installments_60'] ?? 0)
        ];

        LotFinancialTemplate::updateOrCreate(
            ['lot_id' => $lot->lot_id],
            $templateData
        );

        Log::info('[ExternalLotImport] Template financiero actualizado', [
            'lot_id' => $lot->lot_id
        ]);
    }

    /**
     * Parsear valor numérico de string a float
     * Permite valores null para campos opcionales
     * 
     * @param mixed $value
     * @return float|null
     */
    protected function parseNumericValue($value): ?float
    {
        if (is_null($value) || $value === '' || $value === 'N/A') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        // Limpiar formato de moneda (Ej: "$1,234.56" -> 1234.56)
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        return $cleaned !== '' ? (float) $cleaned : null;
    }

    /**
     * Obtener ID de tipo de calle por defecto
     * Como street_type_id es requerido en tu BD, necesitamos un valor por defecto
     * 
     * @return int
     */
    protected function getDefaultStreetTypeId(): int
    {
        return (int) StreetType::firstOrCreate(['name' => 'Sin Especificar'])->street_type_id;
    }

    protected function resolveStreetTypeId(array $property): int
    {
        $value = $this->extractStreetTypeName($property);
        if (!$value) {
            return $this->getDefaultStreetTypeId();
        }

        $normalized = $this->normalizeStreetTypeName($value);
        if ($normalized === '') {
            return $this->getDefaultStreetTypeId();
        }

        $mapped = $this->mapStreetTypeSynonym($normalized);
        $target = $mapped ?: $this->titleizeStreetType($normalized);

        $existing = StreetType::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($target)])
            ->first();

        if ($existing) {
            return (int) $existing->street_type_id;
        }

        return (int) StreetType::firstOrCreate(['name' => $target])->street_type_id;
    }

    protected function extractStreetTypeName(array $property): ?string
    {
        $unitModel = $property['unitModel'] ?? $property['unit_model'] ?? null;
        $unitModelName = null;
        if (is_array($unitModel)) {
            $unitModelName = $unitModel['modName'] ?? $unitModel['name'] ?? null;
        }

        // Priorizar model_name del caché full stock (BOULEVARD, PEATONAL, CALLE = Tipo de calle)
        $candidates = [
            $property['model_name'] ?? null,
            $property['modelName'] ?? null,
            $property['street_type'] ?? null,
            $property['streetType'] ?? null,
            $property['streetTypeName'] ?? null,
            $property['road_type'] ?? null,
            $property['roadType'] ?? null,
            $property['ubicacion'] ?? null,
            $property['UBICACIÓN'] ?? null,
            $unitModelName,
        ];

        $address = $property['address'] ?? null;
        if (is_array($address)) {
            $candidates[] = $address['street_type'] ?? null;
            $candidates[] = $address['streetType'] ?? null;
            $candidates[] = $address['roadType'] ?? null;
        }

        foreach ($candidates as $c) {
            if (is_array($c)) {
                $c = $c['name'] ?? ($c['label'] ?? null);
            }
            if (is_string($c)) {
                $c = trim($c);
                if ($c !== '') return $c;
            }
        }

        return null;
    }

    protected function normalizeStreetTypeName(string $value): string
    {
        $v = trim($value);
        $v = preg_replace('/\s+/', ' ', $v);
        $v = mb_strtolower($v);
        $v = str_replace(['.', ',', ';', ':', '-', '_', '/', '\\'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if (is_string($ascii) && $ascii !== '') {
            $v = $ascii;
        }

        return trim($v);
    }

    protected function mapStreetTypeSynonym(string $normalized): ?string
    {
        $n = trim($normalized);

        if (preg_match('/^(av|avd|avda|avenida)\b/', $n)) return 'Avenida';
        if (preg_match('/^(cl|calle)\b/', $n)) return 'Calle';
        if (preg_match('/^(jr|jiron|jiron)\b/', $n)) return 'Jirón';
        if (preg_match('/^(psj|pje|pasaje)\b/', $n)) return 'Pasaje';
        if (preg_match('/^(peatonal)\b/', $n)) return 'Peatonal';
        if (preg_match('/^(boulevard|bulevar)\b/', $n)) return 'Boulevard';

        return null;
    }

    protected function titleizeStreetType(string $normalized): string
    {
        $n = trim($normalized);
        if ($n === '') return $n;
        $words = array_map(fn ($w) => $w === '' ? '' : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1), explode(' ', $n));
        return implode(' ', $words);
    }

    /**
     * Resetear estadísticas
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        $this->errors = [];
    }

    /**
     * Obtener estadísticas de la última importación
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'stats' => $this->stats,
            'errors' => $this->errors
        ];
    }

    /**
     * Sincronizar un lote específico por su código
     * 
     * @param string $code Código del lote (Ej: "E2-02")
     * @return array
     */
    public function syncLotByCode(string $code): array
    {
        try {
            Log::info('[ExternalLotImport] Sincronizando lote individual', [
                'code' => $code
            ]);

            // Buscar la propiedad en el API
            $properties = $this->apiService->getProperties(['code' => $code]);
            
            if (!isset($properties['data']) || empty($properties['data'])) {
                throw new Exception("Lote no encontrado en API externa: {$code}");
            }

            $property = $properties['data'][0];
            
            DB::beginTransaction();
            try {
                $this->processProperty($property);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return [
                'success' => true,
                'message' => "Lote {$code} sincronizado exitosamente",
                'stats' => $this->stats
            ];

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error sincronizando lote individual', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Importar ventas (contratos) desde la respuesta de LOGICWARE
     * Crea clientes si no existen, enlaza asesor (employee) por nombre y busca lote por código
     * @param string|null $startDate
     * @param string|null $endDate
     * @param bool $forceRefresh
     * @return array
     */
    public function importSales(?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false): array
    {
        $this->resetStats();

        try {
            Log::info('[ExternalLotImport] Iniciando importación de ventas desde LOGICWARE', ['start' => $startDate, 'end' => $endDate]);

            $salesResponse = $this->apiService->getSales($startDate, $endDate, $forceRefresh);

            if (!isset($salesResponse['data']) || !is_array($salesResponse['data'])) {
                throw new Exception('Formato de respuesta inesperado del API para ventas');
            }

            $documents = $salesResponse['data'];
            $this->stats['total'] = count($documents);

            DB::beginTransaction();
            try {
                foreach ($documents as $doc) {
                    // Cada documento representa un cliente con documentos (ventas)
                    $this->processSaleDocument($doc);
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return [
                'success' => $this->stats['errors'] === 0,
                'message' => 'Importación completada',
                'data' => [
                    'stats' => $this->stats,
                    'errors' => $this->errors
                ]
            ];

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error importando ventas', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'errors' => [$e->getMessage()],
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Procesar documento de venta (cliente + documentos[])
     * @param array $doc
     */
    protected function processSaleDocument(array $doc): void
    {
        try {
            // Extraer datos del cliente
            $docNumber = $doc['documentNumber'] ?? null;
            $firstName = $doc['firstName'] ?? null;
            $paternal = $doc['paternalSurname'] ?? null;
            $maternal = $doc['maternalSurname'] ?? null;
            $email = $doc['email'] ?? null;
            $phone = $doc['phone'] ?? null;

            // Buscar cliente por documento o email
            $client = \Modules\CRM\Models\Client::where('doc_number', $docNumber)
                        ->orWhere('email', $email)
                        ->first();

            if (!$client) {
                // Preparar datos completos del cliente
                $birthDate = isset($doc['birthDate']) ? substr($doc['birthDate'],0,10) : null;
                
                $client = \Modules\CRM\Models\Client::create([
                    'first_name' => $firstName ?: ($doc['fullName'] ?? 'N/D'),
                    'last_name' => trim(($paternal ?? '') . ' ' . ($maternal ?? '')),
                    'doc_type' => 'DNI',
                    'doc_number' => $docNumber,
                    'email' => $email,
                    'primary_phone' => $phone,
                    'date' => $birthDate,
                    'type' => 'client',
                    'source' => 'logicware'
                ]);

                // Crear dirección si existe
                if (!empty($doc['address'])) {
                    \Modules\CRM\Models\Address::create([
                        'client_id' => $client->client_id,
                        'line1' => $doc['address'],
                        'line2' => $doc['district'] ?? null,
                        'city' => $doc['province'] ?? null,
                        'state' => $doc['department'] ?? null,
                        'country' => 'PER'
                    ]);
                }

                Log::info('[ExternalLotImport] Cliente creado desde Logicware', [
                    'client_id' => $client->client_id,
                    'doc_number' => $docNumber,
                    'full_name' => $doc['fullName'] ?? 'N/D'
                ]);
            }

            // Procesar cada documento/venta del cliente
            if (!empty($doc['documents']) && is_array($doc['documents'])) {
                foreach ($doc['documents'] as $document) {
                    $this->processSaleDocumentItem($client, $document);
                }
            }

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error procesando sale document', ['error' => $e->getMessage()]);
            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
        }
    }

    protected function upsertClientFromSaleDoc(array $doc)
    {
        $docNumber = $doc['documentNumber'] ?? null;
        $email = $doc['email'] ?? null;

        $client = \Modules\CRM\Models\Client::where('doc_number', $docNumber)
            ->orWhere('email', $email)
            ->first();

        if ($client) {
            return $client;
        }

        $firstName = $doc['firstName'] ?? null;
        $paternal = $doc['paternalSurname'] ?? null;
        $maternal = $doc['maternalSurname'] ?? null;
        $phone = $doc['phone'] ?? null;
        $birthDate = isset($doc['birthDate']) ? substr($doc['birthDate'], 0, 10) : null;

        $client = \Modules\CRM\Models\Client::create([
            'first_name' => $firstName ?: ($doc['fullName'] ?? 'N/D'),
            'last_name' => trim(($paternal ?? '') . ' ' . ($maternal ?? '')),
            'doc_type' => 'DNI',
            'doc_number' => $docNumber,
            'email' => $email,
            'primary_phone' => $phone,
            'date' => $birthDate,
            'type' => 'client',
            'source' => 'logicware'
        ]);

        if (!empty($doc['address'])) {
            \Modules\CRM\Models\Address::create([
                'client_id' => $client->client_id,
                'line1' => $doc['address'],
                'line2' => $doc['district'] ?? null,
                'city' => $doc['province'] ?? null,
                'state' => $doc['department'] ?? null,
                'country' => 'PER'
            ]);
        }

        Log::info('[ExternalLotImport] Cliente creado desde Logicware', [
            'client_id' => $client->client_id,
            'doc_number' => $docNumber,
            'full_name' => $doc['fullName'] ?? 'N/D'
        ]);

        return $client;
    }

    /**
     * Procesar un item de documento (proforma/venta)
     * @param \Modules\CRM\Models\Client $client
     * @param array $document
     */
    protected function processSaleDocumentItem($client, array $document): bool
    {
        try {
            // Buscar asesor por nombre usando score-based matching
            $sellerName = $document['seller'] ?? null;
            $advisorId = null;
            if ($sellerName) {
                $advisor = $this->findAdvisorByName($sellerName);

                if ($advisor) {
                    $advisorId = $advisor->employee_id;
                    Log::info('[ExternalLotImport] ✅ Asesor encontrado', [
                        'seller_name' => $sellerName,
                        'advisor_id' => $advisorId,
                        'advisor_name' => ($advisor->user->first_name ?? '') . ' ' . ($advisor->user->last_name ?? '')
                    ]);
                } else {
                    Log::warning('[ExternalLotImport] ❌ Asesor no encontrado', ['seller_name' => $sellerName]);
                }
            }

            // Obtener unidad/lote del primer unit del documento
            $unit = $document['units'][0] ?? null;
            $unitNumber = $unit['unitNumber'] ?? $unit['code'] ?? null;
            $lotId = null;
            $fullStockStatus = $this->getUnitStatusFromFullStock($unitNumber, $this->currentForceRefresh);

            if ($unitNumber) {
                $parsed = $this->parsePropertyCode($unitNumber);
                if ($parsed) {
                    $manzana = $this->getOrCreateManzana($parsed['manzana']);
                    $lot = \Modules\Inventory\Models\Lot::where('manzana_id', $manzana->manzana_id)
                        ->where('num_lot', (int)$parsed['lote'])
                        ->first();

                    if ($lot) {
                        $lotId = $lot->lot_id;
                        if ($fullStockStatus === 'vendido' || $fullStockStatus === 'reservado' || $fullStockStatus === 'disponible') {
                            if ($lot->status !== $fullStockStatus) {
                                $lot->update(['status' => $fullStockStatus]);
                            }
                        }
                    } else {
                        // Crear el lote si no existe - usando la misma lógica que los contratos
                        $unitPrice = $this->parseNumericValue($unit['unitPrice'] ?? $unit['basePrice'] ?? 0);
                        $discount = $this->parseNumericValue($unit['discount'] ?? 0);
                        $totalPrice = $unitPrice - $discount; // 🔥 Precio final = unitPrice - descuento
                        
                        $lotData = [
                            'manzana_id' => $manzana->manzana_id,
                            'num_lot' => (int)$parsed['lote'],
                            'external_code' => $unitNumber, // 🔥 Guardar el código completo (ej: "I-41")
                            'area_m2' => $this->parseNumericValue($unit['unitArea'] ?? 0),
                            'total_price' => $totalPrice, // 🔥 CORREGIDO: unitPrice - descuento
                            'currency' => strtoupper($unit['currency'] ?? 'PEN'),
                            'status' => in_array($fullStockStatus, ['vendido', 'reservado', 'disponible'], true) ? $fullStockStatus : 'disponible',
                            'street_type_id' => $this->getDefaultStreetTypeId()
                        ];
                        $lot = \Modules\Inventory\Models\Lot::create($lotData);
                        $lotId = $lot->lot_id;
                        
                        Log::info('[ExternalLotImport] Lote creado desde venta', [
                            'unit_number' => $unitNumber,
                            'lot_id' => $lotId,
                            'manzana' => $manzana->name
                        ]);
                    }
                }
            }

            // Construir datos del contrato
            $unit = $document['units'][0] ?? [];
            $financing = $document['financing'] ?? [];
            $unitStatus = strtolower(trim((string) ($unit['status'] ?? $unit['state'] ?? '')));
            if ($unitStatus === '' && is_string($fullStockStatus) && $fullStockStatus !== '') {
                $unitStatus = strtolower(trim($fullStockStatus));
            }
            
            // Extraer datos financieros completos
            $basePrice = $this->parseNumericValue($unit['basePrice'] ?? 0); // 🔥 Precio Base
            $unitPrice = $this->parseNumericValue($unit['unitPrice'] ?? $unit['price'] ?? 0); // 🔥 Precio Unitario (Venta)
            $discount = $this->parseNumericValue($unit['discount'] ?? $financing['discount'] ?? 0); // 🔥 Descuento
            $totalPrice = $this->parseNumericValue($unit['total'] ?? 0); // 🔥 Precio Final (total desde Logicware)
            $reservationAmount = $this->parseNumericValue($financing['reservationAmount'] ?? 0); // 🏷️ Monto de Reserva
            $downPayment = $this->parseNumericValue($financing['downPayment'] ?? 0);
            $financingAmount = $this->parseNumericValue($financing['amountToFinance'] ?? 0);
            $balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
            $bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);
            $bfhBonus = $this->parseNumericValue($financing['bfhBonus'] ?? $financing['bfh'] ?? 0);
            $funding = $this->parseNumericValue($financing['funding'] ?? 0);
            
            $currency = strtoupper($financing['currency'] ?? $unit['currency'] ?? 'PEN');
            $docStatus = strtolower(trim((string) ($document['status'] ?? $document['state'] ?? '')));
            // Document type puede venir como "resuelto" / "cancelado" en algunos APIs
            $docType = strtolower(trim((string) ($document['documentType'] ?? $document['document_type'] ?? $document['type'] ?? '')));

            // Revisar todos los campos donde Logicware podría enviar estado de resolución (si no viene en status/state)
            $docResolution = strtolower(trim((string) (
                $document['resolutionStatus'] ?? $document['resolution_status'] ?? $document['documentStatus'] ?? $document['document_status']
                ?? $document['stage'] ?? $document['phase'] ?? $document['workflowState'] ?? $document['contractState'] ?? ''
            )));
            $unitResolution = strtolower(trim((string) (
                $unit['resolutionStatus'] ?? $unit['resolution_status'] ?? $unit['stage'] ?? $unit['phase'] ?? ''
            )));
            $financingResolution = strtolower(trim((string) (
                $financing['resolutionStatus'] ?? $financing['resolution_status'] ?? $financing['status'] ?? ''
            )));

            // Contrato resuelto = cancelado/desistimiento/dejó de pagar → no cuenta como venta, se mantiene en historial
            // Si Logicware no envía estado "resuelto" en ningún campo, pedir que lo agreguen (ej. resolutionStatus) o usar lista manual en BD.
            $resolvedStatuses = ['resuelto', 'resolved', 'cancelado', 'cancelled', 'canceled', 'anulado', 'rescinded', 'terminated', 'closed_cancelled'];
            $isResolved = in_array($unitStatus, $resolvedStatuses, true)
                || in_array($docStatus, $resolvedStatuses, true)
                || in_array($docType, $resolvedStatuses, true)
                || in_array($docResolution, $resolvedStatuses, true)
                || in_array($unitResolution, $resolvedStatuses, true)
                || in_array($financingResolution, $resolvedStatuses, true);

            // Combinar stock completo + contrato: si el lote está disponible y tiene lock "por resolución"/"Bloqueo Comercial" → resuelto (historial sí, ventas/comisiones no)
            $this->ensureFullStockLoaded($this->currentForceRefresh);
            $normalizedKey = $unitNumber ? $this->normalizeUnitNumber($unitNumber) : null;
            $keyInIndex = $normalizedKey && $this->fullStockByUnitNumber !== null && isset($this->fullStockByUnitNumber[$normalizedKey]);
            $resolvedKeysSample = null;
            if (!$keyInIndex && $normalizedKey && $this->fullStockByUnitNumber !== null) {
                $resolvedKeysSample = array_slice(array_keys(array_filter($this->fullStockByUnitNumber, function ($e) {
                    return is_array($e) && !empty($e['resolved_by_lock']);
                })), 0, 5);
           }
            Log::debug('[ExternalLotImport] Procesando contrato (resolución)', [
                'contract_correlative' => $document['correlative'] ?? null,
                'unit_number_raw' => $unitNumber,
                'unit_number_normalized' => $normalizedKey,
                'key_in_full_stock_index' => $keyInIndex,
                'is_resolved_before_lock_check' => $isResolved,
                'resolved_keys_sample' => $resolvedKeysSample,
            ]);
            if (!$isResolved && $unitNumber) {
                $resolvedByLock = $this->getUnitIsResolvedByLockFromFullStock($unitNumber, $this->currentForceRefresh);
                Log::debug('[ExternalLotImport] Resultado getUnitIsResolvedByLockFromFullStock', [
                    'unit_number' => $unitNumber,
                    'resolved_by_lock' => $resolvedByLock,
                ]);
                if ($resolvedByLock) {
                    $isResolved = true;
                    Log::info('[ExternalLotImport] Contrato marcado como resuelto por lock en full stock', ['unit_number' => $unitNumber]);
                }
            }
            // Fallback: el documento/unit trae lock y el estado del lote es disponible (misma lógica sin depender del cache de stock)
            $unitLockType = (string) ($unit['lockType'] ?? $unit['lock_type'] ?? '');
            $unitLockDesc = (string) ($unit['lockDescription'] ?? $unit['lock_description'] ?? '');
            $lotAvailable = ($unitStatus === 'disponible' || $fullStockStatus === 'disponible');
            Log::debug('[ExternalLotImport] Fallback lock (documento/unit)', [
                'unit_number' => $unitNumber,
                'unit_status' => $unitStatus,
                'full_stock_status' => $fullStockStatus,
                'lock_type' => $unitLockType,
                'lock_description' => $unitLockDesc,
                'lot_available' => $lotAvailable,
            ]);
            if (!$isResolved && $lotAvailable && (stripos($unitLockType, 'Bloqueo Comercial') !== false || stripos($unitLockDesc, 'por resolucion') !== false)) {
                $isResolved = true;
                Log::info('[ExternalLotImport] Contrato marcado como resuelto por lock en documento/unit', ['unit_number' => $unitNumber]);
            }

            $isSale = !$isResolved && (
                in_array($unitStatus, ['vendido', 'venta', 'sale', 'sold'], true)
                || !empty($document['saleStartDate'])
                || in_array($docStatus, ['venta', 'vendido', 'sold', 'sale', 'firmado', 'contrato'], true)
            );

            // Estado del contrato: resuelto (historial, no venta), vigente (venta activa), o pendiente
            $contractStatus = $isResolved ? 'resuelto' : ($isSale ? 'vigente' : 'pendiente_aprobacion');

            $saleDate = $document['saleStartDate']
                ?? $document['saleDate']
                ?? $document['sale_date']
                ?? $document['proformaStartDate']
                ?? $document['separationStartDate']
                ?? now()->toDateString();
            $termMonths = (int)($financing['financingInstallments'] ?? 12);
            $monthlyPayment = $financingAmount > 0 && $termMonths > 0 ? ($financingAmount / $termMonths) : 0;

            $contractNumber = $document['correlative'] ?? null;

            // 🏷️ RESERVA NO ES CONTRATO: Si no hay número de contrato (correlativo), es solo reserva en Logicware.
            // No crear Contract; solo crear/actualizar Reservation y estado del lote. Así no inflamos "Total contratos" ni contamos reservas como ventas.
            $reservationStatuses = ['reservado', 'reserved', 'reserva', 'separación', 'separation', 'bloqueado', 'blocked'];
            $isReservationStatus = in_array($unitStatus, $reservationStatuses, true) || in_array($docStatus, $reservationStatuses, true);
            $hasContractEvidence = !empty($contractNumber)
                || !empty($document['saleStartDate'])
                || in_array($docStatus, ['venta', 'vendido', 'sold', 'sale', 'firmado', 'contrato'], true)
                || in_array($unitStatus, ['vendido', 'venta', 'sale', 'sold'], true);

            if (empty($contractNumber) && ($isReservationStatus || $reservationAmount > 0) && !$hasContractEvidence) {
                // Solo reserva: actualizar lote a reservado, crear/actualizar Reservation, NO crear Contract
                if ($lotId) {
                    \Modules\Inventory\Models\Lot::where('lot_id', $lotId)->update(['status' => 'reservado']);
                }
                if ($reservationAmount > 0 && $lotId && $advisorId) {
                    $reservation = \Modules\Sales\Models\Reservation::where('lot_id', $lotId)
                        ->where('client_id', $client->client_id)
                        ->whereIn('status', ['activa', 'convertida'])
                        ->first();
                    if (!$reservation) {
                        $reservationDate = isset($document['separationStartDate'])
                            ? \Carbon\Carbon::parse($document['separationStartDate'])->format('Y-m-d')
                            : substr($document['separationStartDate'] ?? now()->toDateString(), 0, 10);
                        $expirationDate = isset($document['separationEndDate'])
                            ? \Carbon\Carbon::parse($document['separationEndDate'])->format('Y-m-d')
                            : \Carbon\Carbon::parse($reservationDate)->addDays(30)->format('Y-m-d');
                        \Modules\Sales\Models\Reservation::create([
                            'lot_id' => $lotId,
                            'client_id' => $client->client_id,
                            'advisor_id' => $advisorId,
                            'reservation_date' => $reservationDate,
                            'expiration_date' => $expirationDate,
                            'deposit_amount' => $reservationAmount,
                            'status' => 'activa',
                        ]);
                        Log::info('[ExternalLotImport] 🏷️ Solo reserva: Reservation creada (NO se crea Contract)', [
                            'lot_id' => $lotId,
                            'client_id' => $client->client_id,
                            'deposit_amount' => $reservationAmount,
                        ]);
                    }
                } else {
                    Log::info('[ExternalLotImport] 🏷️ Solo reserva: documento sin correlativo, solo actualizando lote/reserva (NO Contract)', [
                        'unit_number' => $unitNumber,
                        'lot_id' => $lotId,
                        'has_reservation_amount' => $reservationAmount > 0,
                    ]);
                }
                return true;
            }

            // Si no hay correlativo pero el documento parece venta (inconsistente), no crear Contract sin número
            if (empty($contractNumber)) {
                Log::info('[ExternalLotImport] ⏭️ Documento sin número de contrato (correlativo), omitiendo creación de Contract', [
                    'unit_number' => $unitNumber,
                    'doc_status' => $docStatus,
                    'unit_status' => $unitStatus,
                ]);
                return true;
            }

            // 🔍 VERIFICAR SI EL CONTRATO YA EXISTE
            // Prioridad 1: Buscar por contract_number (si existe)
            $existingContract = null;
            if ($contractNumber) {
                $existingContract = \Modules\Sales\Models\Contract::where('contract_number', $contractNumber)->first();
            }
            
            // Prioridad 2: Si no se encontró por número, buscar por lote (evita duplicados en mismo lote)
            if (!$existingContract && $lotId) {
                $existingContract = \Modules\Sales\Models\Contract::where('lot_id', $lotId)
                    ->where('client_id', $client->client_id)
                    ->whereIn('status', ['vigente', 'activo'])
                    ->first();
                    
                if ($existingContract) {
                    Log::info('[ExternalLotImport] 🔍 Contrato existente encontrado por lote', [
                        'contract_id' => $existingContract->contract_id,
                        'lot_id' => $lotId,
                        'client_id' => $client->client_id
                    ]);
                }
            }

            // 🏷️ MANEJAR RESERVA SI EXISTE
            $reservationId = null;
            if ($reservationAmount > 0 && $lotId) {
                // Buscar reserva existente para este lote y cliente
                $reservation = \Modules\Sales\Models\Reservation::where('lot_id', $lotId)
                    ->where('client_id', $client->client_id)
                    ->whereIn('status', ['activa', 'convertida'])
                    ->first();

                if (!$reservation) {
                    // Solo crear reserva si tenemos advisor_id válido
                    if (!$advisorId) {
                        Log::warning('[ExternalLotImport] ⚠️ No se puede crear reserva sin advisor_id', [
                            'client_id' => $client->client_id,
                            'lot_id' => $lotId,
                            'seller' => $sellerName
                        ]);
                    } else {
                        // Crear nueva reserva con los datos de Logicware
                        $reservationDate = isset($document['separationStartDate']) 
                            ? \Carbon\Carbon::parse($document['separationStartDate'])->format('Y-m-d')
                            : substr($saleDate, 0, 10);
                        
                        $expirationDate = isset($document['separationEndDate']) 
                            ? \Carbon\Carbon::parse($document['separationEndDate'])->format('Y-m-d')
                            : \Carbon\Carbon::parse($reservationDate)->addDays(30)->format('Y-m-d');

                        $reservation = \Modules\Sales\Models\Reservation::create([
                            'lot_id' => $lotId,
                            'client_id' => $client->client_id,
                            'advisor_id' => $advisorId,
                            'reservation_date' => $reservationDate,
                            'expiration_date' => $expirationDate,
                            'deposit_amount' => $reservationAmount,
                            'status' => 'convertida' // Ya se convirtió en venta
                        ]);

                        Log::info('[ExternalLotImport] 🏷️ Reserva creada desde Logicware', [
                            'reservation_id' => $reservation->reservation_id,
                            'client_id' => $client->client_id,
                            'lot_id' => $lotId,
                            'deposit_amount' => $reservationAmount,
                            'reservation_date' => $reservationDate
                        ]);
                    }
                }

                if ($reservation) {
                    $reservationId = $reservation->reservation_id;
                }
            }

            // Preparar datos del contrato
            // ⚠️ IMPORTANTE: El constraint chk_contract_source requiere:
            //   - O SOLO reservation_id (sin client_id ni lot_id)
            //   - O SOLO client_id + lot_id (sin reservation_id)
            // Como vienen de Logicware, usamos client_id + lot_id SIN reservation_id
            $contractData = [
                'client_id' => $client->client_id,
                'lot_id' => $lotId,
                'advisor_id' => $advisorId,
                // NO incluir reservation_id para cumplir constraint chk_contract_source
                // 'reservation_id' => $reservationId,
                'contract_number' => $contractNumber,
                'contract_date' => substr($saleDate,0,10),
                'sign_date' => substr($saleDate,0,10),
                'base_price' => $basePrice, // 🔥 NUEVO: Precio base de lista
                'unit_price' => $unitPrice, // 🔥 NUEVO: Precio unitario antes de descuento
                'discount' => $discount, // 🔥 Descuento aplicado
                'total_price' => $totalPrice, // 🔥 Precio final (unit total desde Logicware)
                'down_payment' => $downPayment,
                'financing_amount' => $financingAmount,
                'balloon_payment' => $balloonPayment, // 🔥 Cuota balón
                'funding' => $funding,
                'bpp' => $bppBonus, // 🔥 Bono buen pagador
                'bfh' => $bfhBonus,
                'initial_quota' => $downPayment,
                'interest_rate' => 0,
                'term_months' => $termMonths,
                'monthly_payment' => $this->parseNumericValue($monthlyPayment) ?? 0,
                'currency' => $currency,
                'status' => $contractStatus,
                'source' => 'logicware', // 🔥 Identificar fuente
                'logicware_data' => json_encode(array_replace_recursive($document, [
                    'unit_status' => $unitStatus ?: null,
                    'units' => !empty($document['units']) && is_array($document['units']) ? array_replace_recursive($document['units'], [
                        0 => ['status' => $unitStatus ?: null]
                    ]) : $document['units'] ?? [],
                ])) // 🔥 Guardar datos completos para re-linkeo futuro
            ];

            if ($existingContract) {
                // 🔄 ACTUALIZAR CONTRATO EXISTENTE
                // ⚠️ NO actualizamos reservation_id en contratos existentes para no violar constraint
                unset($contractData['reservation_id']); // Remover reservation_id del update
                if (($existingContract->source ?? null) !== 'logicware' && $existingContract->status === 'vigente' && !$isSale && !$isResolved) {
                    unset($contractData['status']);
                }
                // Si Logicware no envía "resuelto" pero en Casa Bonita ya está marcado como resuelto, no sobrescribir a vigente
                if ($existingContract->status === 'resuelto' && $contractStatus !== 'resuelto') {
                    unset($contractData['status']);
                }
                $existingContract->update($contractData);
                $contract = $existingContract;
                $this->stats['updated']++;

                if ($contractStatus === 'resuelto' && $lotId) {
                    \Modules\Inventory\Models\Lot::where('lot_id', $lotId)->update(['status' => 'disponible']);
                    Log::info('[ExternalLotImport] 📋 Contrato resuelto: lote liberado a disponible', ['lot_id' => $lotId]);
                }

                Log::info('[ExternalLotImport] 🔄 Contrato actualizado con datos desde Logicware', [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contractNumber,
                    'base_price' => $basePrice,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total_price' => $totalPrice,
                    'balloon_payment' => $balloonPayment,
                    'bpp' => $bppBonus
                ]);
            } else {
                // ✨ CREAR NUEVO CONTRATO
                $contract = \Modules\Sales\Models\Contract::create($contractData);
                $this->stats['created']++;

                Log::info('[ExternalLotImport] 💰 Contrato creado con datos financieros completos', [
                    'contract_id' => $contract->contract_id,
                    'contract_number' => $contractNumber,
                    'has_reservation' => $reservationId ? 'Sí' : 'No',
                    'reservation_id' => $reservationId,
                    'base_price' => $basePrice,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total_price' => $totalPrice,
                    'balloon_payment' => $balloonPayment,
                    'bpp' => $bppBonus
                ]);

                if ($contractStatus === 'resuelto' && $lotId) {
                    \Modules\Inventory\Models\Lot::where('lot_id', $lotId)->update(['status' => 'disponible']);
                    Log::info('[ExternalLotImport] 📋 Contrato resuelto (nuevo): lote liberado a disponible', ['lot_id' => $lotId]);
                }
            }

            // 🔄 SINCRONIZAR CRONOGRAMA DESDE LOGICWARE (incluye estado de pagos)
            $correlative = $document['correlative'] ?? null;
            
            if ($correlative) {
                try {
                    Log::info('[ExternalLotImport] Intentando sincronización de cronograma', [
                        'contract_id' => $contract->contract_id,
                        'correlative' => $correlative
                    ]);
                    
                    $this->syncPaymentScheduleFromLogicware($contract, $correlative);
                    
                    Log::info('[ExternalLotImport] ✅ Cronograma sincronizado desde Logicware', [
                        'contract_id' => $contract->contract_id,
                        'correlative' => $correlative
                    ]);
                    
                    // ⏱️ RATE LIMIT: Esperar 350ms entre peticiones (máx 200/min = 1 cada 300ms)
                    usleep(350000); // 350 milisegundos
                    
                } catch (Exception $scheduleError) {
                    Log::warning('[ExternalLotImport] ⚠️ No se pudo sincronizar cronograma desde Logicware, usando fallback', [
                        'contract_id' => $contract->contract_id,
                        'correlative' => $correlative,
                        'error' => $scheduleError->getMessage()
                    ]);
                    
                    // Si es error 429 (rate limit), esperar 2 segundos antes de continuar
                    if (strpos($scheduleError->getMessage(), '429') !== false) {
                        Log::info('[ExternalLotImport] ⏸️ Rate limit detectado, esperando 2 segundos...');
                        sleep(2);
                    }
                    
                    // Fallback: generar cronograma tradicional
                    $financing = $document['financing'] ?? [];
                    if (!empty($financing['totalInstallments'])) {
                        $this->generatePaymentSchedule($contract, $financing, substr($saleDate,0,10));
                        Log::info('[ExternalLotImport] Cronograma generado con método tradicional (fallback)');
                    }
                }
            } else {
                // Si no hay correlativo, usar método tradicional
                Log::info('[ExternalLotImport] No hay correlativo, usando cronograma tradicional');
                $financing = $document['financing'] ?? [];
                if (!empty($financing['totalInstallments'])) {
                    $this->generatePaymentSchedule($contract, $financing, substr($saleDate,0,10));
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error procesando documento item', ['error' => $e->getMessage()]);
            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
            return false;
        }
    }

    /**
     * Generar cronograma de pagos (cuotas) para un contrato
     * Incluye: Cuotas iniciales, financiamiento, cuota balón y bono BPP
     * 
     * @param \Modules\Sales\Models\Contract $contract
     * @param array $financing Datos de financiamiento desde LOGICWARE
     * @param string $startDate Fecha de inicio del contrato
     * @return void
     */
    protected function generatePaymentSchedule($contract, array $financing, string $startDate): void
    {
        try {
            $totalInstallments = (int)($financing['totalInstallments'] ?? 0);
            $initialInstallments = (int)($financing['initialInstallments'] ?? 1);
            $financingInstallments = (int)($financing['financingInstallments'] ?? 0);
            $downPayment = $this->parseNumericValue($financing['downPayment'] ?? 0);
            $amountToFinance = $this->parseNumericValue($financing['amountToFinance'] ?? 0);
            $balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
            $bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);
            
            if ($totalInstallments <= 0) {
                Log::warning('[ExternalLotImport] No se generan cuotas, totalInstallments es 0', [
                    'contract_id' => $contract->contract_id
                ]);
                return;
            }

            $baseDate = \Carbon\Carbon::parse($startDate);
            $installmentNumber = 1;

            // 1. Crear cuota(s) inicial(es)
            if ($downPayment > 0 && $initialInstallments > 0) {
                $initialPaymentAmount = $downPayment / $initialInstallments;
                
                for ($i = 0; $i < $initialInstallments; $i++) {
                    \Modules\Sales\Models\PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber++,
                        'due_date' => $baseDate->copy()->addMonths($i)->toDateString(),
                        'amount' => $initialPaymentAmount,
                        'status' => 'pendiente',
                        'type' => 'inicial',
                        'currency' => $contract->currency
                    ]);
                }
            }

            // 2. Crear cuotas de financiamiento
            if ($financingInstallments > 0 && $amountToFinance > 0) {
                $monthlyPayment = $amountToFinance / $financingInstallments;
                
                for ($i = 0; $i < $financingInstallments; $i++) {
                    \Modules\Sales\Models\PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber++,
                        'due_date' => $baseDate->copy()->addMonths($initialInstallments + $i)->toDateString(),
                        'amount' => $monthlyPayment,
                        'status' => 'pendiente',
                        'type' => 'financiamiento',
                        'currency' => $contract->currency
                    ]);
                }
            }
            
            // 3. 🔥 Crear cuota BALÓN (si existe)
            if ($balloonPayment > 0) {
                \Modules\Sales\Models\PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $installmentNumber++,
                    'due_date' => $baseDate->copy()->addMonths($initialInstallments + $financingInstallments)->toDateString(),
                    'amount' => $balloonPayment,
                    'status' => 'pendiente',
                    'type' => 'balon',
                    'currency' => $contract->currency,
                    'notes' => 'Cuota Balón'
                ]);
                
                Log::info('[ExternalLotImport] 🎈 Cuota balón agregada', [
                    'contract_id' => $contract->contract_id,
                    'amount' => $balloonPayment
                ]);
            }
            
            // 4. 🔥 Crear cuota BONO BPP / Buen Pagador (si existe)
            if ($bppBonus > 0) {
                \Modules\Sales\Models\PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $installmentNumber++,
                    'due_date' => $baseDate->copy()->addMonths($initialInstallments + $financingInstallments + 1)->toDateString(),
                    'amount' => $bppBonus,
                    'status' => 'pendiente',
                    'type' => 'bono_bpp',
                    'currency' => $contract->currency,
                    'notes' => 'Bono Buen Pagador'
                ]);
                
                Log::info('[ExternalLotImport] 🎁 Cuota BPP agregada', [
                    'contract_id' => $contract->contract_id,
                    'amount' => $bppBonus
                ]);
            }

            Log::info('[ExternalLotImport] ✅ Cuotas generadas completas', [
                'contract_id' => $contract->contract_id,
                'total_installments' => $totalInstallments,
                'initial_installments' => $initialInstallments,
                'financing_installments' => $financingInstallments,
                'balloon_payment' => $balloonPayment > 0 ? 'Sí' : 'No',
                'bpp_bonus' => $bppBonus > 0 ? 'Sí' : 'No',
                'total_cuotas_generadas' => $installmentNumber - 1
            ]);

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error generando cuotas', [
                'contract_id' => $contract->contract_id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Buscar asesor por nombre usando algoritmo de score-based matching
     * Mismo algoritmo que LogicwareContractImporter
     * 
     * @param string|null $sellerName Nombre del vendedor desde Logicware (ej: "PAOLA CANDELA")
     * @return \Modules\HumanResources\Models\Employee|null
     */
    protected function findAdvisorByName(?string $sellerName)
    {
        if (!$sellerName) {
            return null;
        }

        // Limpiar y separar nombre
        $sellerName = trim($sellerName);
        $sellerParts = array_filter(explode(' ', strtoupper($sellerName)));

        Log::debug('[ExternalLotImport] 🔍 Buscando asesor', [
            'seller_from_api' => $sellerName,
            'seller_parts' => $sellerParts
        ]);

        // Obtener todos los empleados con usuario
        $allAdvisors = \Modules\HumanResources\Models\Employee::whereHas('user')->with('user')->get();
        $bestMatch = null;
        $bestScore = 0;

        foreach ($allAdvisors as $advisor) {
            $firstName = strtoupper($advisor->user->first_name ?? '');
            $lastName = strtoupper($advisor->user->last_name ?? '');
            $advisorFullName = trim($firstName . ' ' . $lastName);
            $advisorParts = array_filter(explode(' ', $advisorFullName));
            
            $score = 0;
            $matchedParts = 0;
            
            // Calcular score: buscar cada parte del seller en el advisor
            foreach ($sellerParts as $sellerPart) {
                $foundExactMatch = false;
                $foundPartialMatch = false;
                
                foreach ($advisorParts as $advisorPart) {
                    // Coincidencia exacta de palabra completa (mayor peso)
                    if ($advisorPart === $sellerPart) {
                        $score += 100;
                        $foundExactMatch = true;
                        $matchedParts++;
                        break;
                    }
                    // Coincidencia como substring (menor peso)
                    elseif (stripos($advisorPart, $sellerPart) !== false) {
                        $score += 50;
                        $foundPartialMatch = true;
                        $matchedParts++;
                        break;
                    }
                }
                
                // Si es parte del first_name o last_name directamente
                if (!$foundExactMatch && !$foundPartialMatch) {
                    if (stripos($firstName, $sellerPart) !== false) {
                        $score += 30;
                        $matchedParts++;
                    } elseif (stripos($lastName, $sellerPart) !== false) {
                        $score += 30;
                        $matchedParts++;
                    }
                }
            }
            
            // BONUS CRÍTICO: Si todas las partes del seller coinciden
            if ($matchedParts === count($sellerParts)) {
                $score += 500;
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $advisor;
            }
        }

        // Requerir score mínimo
        if (!$bestMatch || $bestScore < 100) {
            Log::warning('[ExternalLotImport] ❌ Asesor NO encontrado (score insuficiente)', [
                'seller_name' => $sellerName,
                'best_score' => $bestScore,
                'required_score' => 100
            ]);
            return null;
        }

        Log::info('[ExternalLotImport] ✅ Asesor vinculado con score-based matching', [
            'employee_id' => $bestMatch->employee_id,
            'user_name' => ($bestMatch->user->first_name ?? '') . ' ' . ($bestMatch->user->last_name ?? ''),
            'seller_from_api' => $sellerName,
            'match_score' => $bestScore
        ]);

        return $bestMatch;
    }

    /**
     * Sincronizar cronograma de pagos desde Logicware usando el endpoint /external/payment-schedules/{correlative}
     * Este método reemplaza generatePaymentSchedule() para obtener los datos reales de Logicware
     * 
     * @param \Modules\Sales\Models\Contract $contract
     * @param string $correlative Número de correlativo del contrato en Logicware
     * @return void
     * @throws Exception
     */
    protected function syncPaymentScheduleFromLogicware($contract, string $correlative): void
    {
        Log::info('[ExternalLotImport] 🔄 Sincronizando cronograma desde Logicware', [
            'contract_id' => $contract->contract_id,
            'correlative' => $correlative
        ]);

        try {
            // Obtener cronograma completo desde Logicware con timeout extendido
            $scheduleData = $this->apiService->getPaymentSchedule($correlative, false);
            
            if (!isset($scheduleData['data']['installments']) || !is_array($scheduleData['data']['installments'])) {
                Log::warning('[ExternalLotImport] No se recibieron installments desde Logicware', [
                    'response_keys' => array_keys($scheduleData['data'] ?? [])
                ]);
                throw new Exception('No se recibieron installments desde Logicware');
            }
        } catch (\Exception $e) {
            Log::error('[ExternalLotImport] Error obteniendo cronograma desde Logicware', [
                'correlative' => $correlative,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        $installments = $scheduleData['data']['installments'];
        $totalInstallments = count($installments);
        $createdCount = 0;
        $updatedCount = 0;
        $paidCount = 0;

        Log::info('[ExternalLotImport] Cuotas recibidas desde Logicware', [
            'total' => $totalInstallments
        ]);

        foreach ($installments as $inst) {
            // 🎯 DETECCIÓN INTELIGENTE DE TIPO POR ETIQUETA
            $label = strtolower($inst['label'] ?? '');
            $type = 'otro';

            if (strpos($label, 'inicial') !== false || 
                strpos($label, 'separación') !== false || 
                strpos($label, 'reserva') !== false) {
                $type = 'inicial';
            } elseif (strpos($label, 'balon') !== false || 
                      strpos($label, 'balón') !== false) {
                $type = 'balon';  // 🎈 CUOTA BALÓN
            } elseif (strpos($label, 'pagador') !== false || 
                      strpos($label, 'bpp') !== false || 
                      strpos($label, 'buen pagador') !== false) {
                $type = 'bono_bpp';  // 🎁 BONO BPP
            } elseif (strpos($label, 'financiar') !== false || 
                      strpos($label, 'cuota') !== false) {
                $type = 'financiamiento';
            }

            // 💰 DETECCIÓN DE ESTADO DE PAGO DESDE LOGICWARE
            $totalPaid = $this->parseNumericValue($inst['totalPaidAmount'] ?? 0);
            $payment = $this->parseNumericValue($inst['payment'] ?? 0);
            $remainingBalance = $this->parseNumericValue($inst['remainingBalance'] ?? $payment);
            
            $logicwareStatus = ($totalPaid >= $payment || 
                               $remainingBalance == 0 || 
                               strtoupper($inst['status'] ?? '') === 'PAID') ? 'pagado' : 'pendiente';
            
            $paidDate = isset($inst['paymentDate']) 
                ? \Carbon\Carbon::parse($inst['paymentDate'])->format('Y-m-d')
                : null;

            // 🔍 BUSCAR SI LA CUOTA YA EXISTE LOCALMENTE
            $installmentNumber = (int)($inst['installmentNumber'] ?? 0);
            $existingSchedule = \Modules\Sales\Models\PaymentSchedule::where('contract_id', $contract->contract_id)
                ->where('installment_number', $installmentNumber)
                ->first();

            // 📝 PREPARAR DATOS DE LA CUOTA
            $scheduleData = [
                'contract_id' => $contract->contract_id,
                'installment_number' => $installmentNumber,
                'due_date' => isset($inst['dueDate']) 
                    ? \Carbon\Carbon::parse($inst['dueDate'])->format('Y-m-d')
                    : null,
                'amount' => $payment,
                'type' => $type,
                'description' => $inst['label'] ?? null
            ];

            if ($existingSchedule) {
                // 🔄 MERGE INTELIGENTE: Preservar estado local si ya está pagado
                $finalStatus = $existingSchedule->status === 'pagado' ? 'pagado' : $logicwareStatus;
                $finalPaidDate = $existingSchedule->paid_date ?? $paidDate;

                // Actualizar cuota existente (mantener el payment status local si es "pagado")
                $existingSchedule->update(array_merge($scheduleData, [
                    'status' => $finalStatus,
                    'paid_date' => $finalPaidDate
                ]));

                $updatedCount++;

                if ($finalStatus === 'pagado') {
                    $paidCount++;
                }

                Log::debug('[ExternalLotImport] Cuota actualizada (merge inteligente)', [
                    'installment_number' => $installmentNumber,
                    'local_status' => $existingSchedule->status,
                    'logicware_status' => $logicwareStatus,
                    'final_status' => $finalStatus
                ]);
            } else {
                // ✨ CREAR NUEVA CUOTA
                \Modules\Sales\Models\PaymentSchedule::create(array_merge($scheduleData, [
                    'status' => $logicwareStatus,
                    'paid_date' => $paidDate
                ]));

                $createdCount++;

                if ($logicwareStatus === 'pagado') {
                    $paidCount++;
                }
            }
        }

        Log::info('[ExternalLotImport] ✅ Cronograma sincronizado con merge inteligente', [
            'contract_id' => $contract->contract_id,
            'total_installments' => $totalInstallments,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'paid' => $paidCount
        ]);
    }
}
