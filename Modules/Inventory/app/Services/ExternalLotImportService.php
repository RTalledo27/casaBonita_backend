<?php

namespace Modules\Inventory\Services;

use App\Services\LogicwareApiService;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\ManzanaFinancingRule;
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

    /**
     * Importar todos los lotes disponibles desde el API externa
     * 
     * @param array $options Opciones de importación
     * @return array Estadísticas de la importación
     */
    public function importLots(array $options = []): array
    {
        $this->resetStats();
        
        try {
            Log::info('[ExternalLotImport] Iniciando importación de lotes externos');

            // Obtener propiedades del API
            $properties = $this->apiService->getAvailableProperties();
            
            if (!isset($properties['data']) || !is_array($properties['data'])) {
                throw new Exception('Formato de respuesta inesperado del API');
            }

            $this->stats['total'] = count($properties['data']);
            
            Log::info('[ExternalLotImport] Propiedades obtenidas', [
                'total' => $this->stats['total']
            ]);

            DB::beginTransaction();
            
            try {
                foreach ($properties['data'] as $property) {
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
     * Procesar una propiedad individual del API
     * 
     * @param array $property Datos de la propiedad
     * @param array $options Opciones de procesamiento
     */
    protected function processProperty(array $property, array $options = []): void
    {
        try {
            // Extraer y validar el código (Ej: "E2-02")
            $code = $property['code'] ?? null;
            
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
        // Mapeo de estados del API externa a nuestros estados (solo 3 estados según tu BD)
        $statusMap = [
            'Disponible' => 'disponible',
            'Bloqueado' => 'reservado',
            'Vendido' => 'vendido',
            'Reservado' => 'reservado',
            'Available' => 'disponible',
            'Reserved' => 'reservado',
            'Sold' => 'vendido',
            'Blocked' => 'reservado'
        ];
        
        $externalStatus = $property['status'] ?? 'Disponible';
        $status = $statusMap[$externalStatus] ?? 'disponible';

        return [
            'manzana_id' => $manzana->manzana_id,
            'num_lot' => (int) $parsed['lote'], // tinyInteger según tu migración
            'area_m2' => $this->parseNumericValue($property['area'] ?? 0),
            'area_construction_m2' => $this->parseNumericValue($property['construction_area'] ?? null),
            'total_price' => $this->parseNumericValue($property['price'] ?? 0),
            'currency' => strtoupper($property['currency'] ?? 'PEN'),
            'status' => $status,
            'street_type_id' => $this->getDefaultStreetTypeId(), // Requerido según tu migración
            
            // Campos de sincronización con API externa
            'external_id' => $property['id'] ?? null,
            'external_code' => $property['code'] ?? null,
            'external_sync_at' => now(),
            'external_data' => [
                'name' => $property['name'] ?? null,
                'block' => $property['block'] ?? null,
                'project' => $property['project'] ?? null,
                'raw_data' => $property // Guardar todos los datos originales
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
        // Buscar o crear un tipo de calle por defecto
        $streetType = DB::table('street_types')
            ->where('name', 'Sin Especificar')
            ->orWhere('name', 'like', '%defecto%')
            ->orWhere('name', 'like', '%default%')
            ->first();
        
        if ($streetType) {
            return $streetType->street_type_id;
        }
        
        // Si no existe, obtener el primer tipo de calle disponible
        $firstStreetType = DB::table('street_types')
            ->orderBy('street_type_id')
            ->first();
        
        if ($firstStreetType) {
            Log::info('[ExternalLotImport] Usando primer tipo de calle como default', [
                'street_type_id' => $firstStreetType->street_type_id,
                'name' => $firstStreetType->name ?? 'N/A'
            ]);
            return $firstStreetType->street_type_id;
        }
        
        // Si no hay ninguno, crear uno por defecto
        $newStreetTypeId = DB::table('street_types')->insertGetId([
            'name' => 'Sin Especificar'
        ]);
        
        Log::info('[ExternalLotImport] Tipo de calle por defecto creado', [
            'street_type_id' => $newStreetTypeId
        ]);
        
        return $newStreetTypeId;
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
                $client = \Modules\CRM\Models\Client::create([
                    'first_name' => $firstName ?: ($doc['fullName'] ?? 'N/D'),
                    'last_name' => trim(($paternal ?? '') . ' ' . ($maternal ?? '')),
                    'doc_type' => 'DNI',
                    'doc_number' => $docNumber,
                    'email' => $email,
                    'primary_phone' => $phone,
                    'date' => isset($doc['birthDate']) ? substr($doc['birthDate'],0,10) : null
                ]);

                // Crear dirección si existe
                if (!empty($doc['address'])) {
                    \Modules\CRM\Models\Address::create([
                        'client_id' => $client->client_id,
                        'line1' => $doc['address'],
                        'city' => $doc['province'] ?? null,
                        'state' => $doc['department'] ?? null,
                        'country' => 'PER'
                    ]);
                }

                $this->stats['created']++;
            } else {
                $this->stats['updated']++;
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

    /**
     * Procesar un item de documento (proforma/venta)
     * @param \Modules\CRM\Models\Client $client
     * @param array $document
     */
    protected function processSaleDocumentItem($client, array $document): void
    {
        try {
            // Buscar asesor por nombre
            $sellerName = $document['seller'] ?? null;
            $advisorId = null;
            if ($sellerName) {
                $advisor = \Modules\HumanResources\Models\Employee::whereHas('user', function ($q) use ($sellerName) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$sellerName]);
                })->first();

                if ($advisor) {
                    $advisorId = $advisor->employee_id;
                } else {
                    Log::warning('[ExternalLotImport] Asesor no encontrado', ['seller_name' => $sellerName]);
                    // Podríamos crear el asesor aquí, pero por ahora solo advertimos
                }
            }

            // Obtener unidad/lote del primer unit del documento
            $unit = $document['units'][0] ?? null;
            $unitNumber = $unit['unitNumber'] ?? null;
            $lotId = null;

            if ($unitNumber) {
                $parsed = $this->parsePropertyCode($unitNumber);
                if ($parsed) {
                    $manzana = $this->getOrCreateManzana($parsed['manzana']);
                    $lot = \Modules\Inventory\Models\Lot::where('manzana_id', $manzana->manzana_id)
                        ->where('num_lot', (int)$parsed['lote'])
                        ->first();

                    if ($lot) {
                        $lotId = $lot->lot_id;
                    } else {
                        // Crear el lote si no existe
                        $lotData = [
                            'manzana_id' => $manzana->manzana_id,
                            'num_lot' => (int)$parsed['lote'],
                            'area_m2' => $this->parseNumericValue($unit['unitArea'] ?? 0),
                            'total_price' => $this->parseNumericValue($unit['total'] ?? 0),
                            'currency' => strtoupper($unit['currency'] ?? 'PEN'),
                            'status' => 'vendido', // Ya está vendido
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
            $total = $unit['total'] ?? ($document['financing']['totalPending'] ?? 0);
            $downPayment = $document['financing']['downPayment'] ?? 0;
            $financingAmount = $document['financing']['amountToFinance'] ?? 0;
            $currency = $document['financing']['currency'] ?? ($unit['currency'] ?? 'PEN');
            $saleDate = $document['saleStartDate'] ?? $document['proformaStartDate'] ?? now()->toDateString();
            $termMonths = (int)($document['financing']['financingInstallments'] ?? 12);
            $monthlyPayment = $financingAmount > 0 && $termMonths > 0 ? ($financingAmount / $termMonths) : 0;

            // Crear contrato directo
            $contractData = [
                'client_id' => $client->client_id,
                'lot_id' => $lotId,
                'advisor_id' => $advisorId,
                'contract_number' => $document['correlative'] ?? null,
                'contract_date' => substr($saleDate,0,10),
                'sign_date' => substr($saleDate,0,10),
                'total_price' => $this->parseNumericValue($total) ?? 0,
                'down_payment' => $this->parseNumericValue($downPayment) ?? 0,
                'financing_amount' => $this->parseNumericValue($financingAmount) ?? 0,
                'funding' => 0,
                'bpp' => 0,
                'bfh' => 0,
                'initial_quota' => $this->parseNumericValue($downPayment) ?? 0,
                'interest_rate' => 0,
                'term_months' => $termMonths,
                'monthly_payment' => $this->parseNumericValue($monthlyPayment) ?? 0,
                'currency' => strtoupper($currency ?? 'PEN'),
                'status' => 'vigente'
            ];

            $contract = \Modules\Sales\Models\Contract::create($contractData);
            $this->stats['created']++;

            // Generar cuotas automáticamente si hay financiamiento
            $financing = $document['financing'] ?? [];
            if (!empty($financing['totalInstallments']) && $contract) {
                $this->generatePaymentSchedule($contract, $financing, substr($saleDate,0,10));
            }

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error procesando documento item', ['error' => $e->getMessage()]);
            $this->stats['errors']++;
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Generar cronograma de pagos (cuotas) para un contrato
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

            Log::info('[ExternalLotImport] Cuotas generadas automáticamente', [
                'contract_id' => $contract->contract_id,
                'total_installments' => $totalInstallments,
                'initial_installments' => $initialInstallments,
                'financing_installments' => $financingInstallments
            ]);

        } catch (Exception $e) {
            Log::error('[ExternalLotImport] Error generando cuotas', [
                'contract_id' => $contract->contract_id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
