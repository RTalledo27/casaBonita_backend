<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Modules\HumanResources\Models\Employee;
use Carbon\Carbon;

/**
 * Servicio para importar contratos desde Logicware
 * 
 * Toma ventas del endpoint /external/clients/sales y:
 * 1. Crea o actualiza clientes
 * 2. Vincula lotes por código (propertyCode)
 * 3. **VINCULA** asesores existentes por employee_code (NO crea nuevos)
 * 4. Crea contratos o reservas según configuración
 */
class LogicwareContractImporter
{
    protected $logicwareService;

    public function __construct(LogicwareApiService $logicwareService)
    {
        $this->logicwareService = $logicwareService;
    }

    /**
     * Importar contratos desde Logicware en un rango de fechas
     * 
     * @param string|null $startDate Formato YYYY-MM-DD
     * @param string|null $endDate Formato YYYY-MM-DD
     * @param bool $forceRefresh Forzar actualización (consume request del límite diario)
     * @return array Resumen de la importación
     * @throws Exception
     */
    public function importContracts(?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false): array
    {
        try {
            Log::info('[LogicwareImporter] 🚀 Iniciando importación de contratos', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'force_refresh' => $forceRefresh
            ]);

            // 1. Obtener ventas desde Logicware
            $salesData = $this->logicwareService->getSales($startDate, $endDate, $forceRefresh);

            if (!isset($salesData['data']) || !is_array($salesData['data'])) {
                throw new Exception('Formato de respuesta inválido: falta "data"');
            }

            $sales = $salesData['data'];
            $totalSales = count($sales);

            Log::info('[LogicwareImporter] Ventas obtenidas', ['total' => $totalSales]);

            // 2. Procesar cada venta
            $results = [
                'total_sales' => $totalSales,
                'contracts_created' => 0,
                'contracts_skipped' => 0,
                'errors' => [],
                'warnings' => []
            ];

            DB::beginTransaction();

            foreach ($sales as $index => $sale) {
                try {
                    $currentSale = $index + 1;
                    Log::info("[LogicwareImporter] Procesando venta {$currentSale}/{$totalSales}", [
                        'sale_id' => $sale['id'] ?? 'N/A',
                        'document_number' => $sale['documentNumber'] ?? 'N/A'
                    ]);

                    $contractResult = $this->processSale($sale);

                    if ($contractResult['created']) {
                        $results['contracts_created']++;
                    } else {
                        $results['contracts_skipped']++;
                        if (isset($contractResult['reason'])) {
                            $results['warnings'][] = $contractResult['reason'];
                        }
                    }

                } catch (Exception $e) {
                    $results['errors'][] = [
                        'sale_id' => $sale['id'] ?? 'N/A',
                        'document_number' => $sale['documentNumber'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                    Log::error('[LogicwareImporter] Error procesando venta', [
                        'sale_id' => $sale['id'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info('[LogicwareImporter] ✅ Importación completada', $results);

            return $results;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[LogicwareImporter] Error crítico en importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Procesar una venta individual
     * 
     * Estructura REAL del API de Logicware:
     * {
     *   "documentNumber": "12345678",
     *   "fullName": "JUAN PEREZ",
     *   "firstName": "JUAN",
     *   "paternalSurname": "PEREZ",
     *   "phone": "+51987654321",
     *   "email": "juan@example.com",
     *   "documents": [
     *     {
     *       "proformaId": 3235,
     *       "correlative": "202511-000000577",
     *       "status": "Ventas",
     *       "saleStartDate": "2025-11-15T18:16:59.313",
     *       "seller": "DAVID FEIJOO",
     *       "units": [
     *         {
     *           "unitNumber": "G2-16",
     *           "unitArea": 72.0,
     *           "basePrice": 33600.0,
     *           "total": 28560.0
     *         }
     *       ],
     *       "financing": {
     *         "currency": "PEN",
     *         "downPayment": 376.0,
     *         "amountToFinance": 22184.0,
     *         "totalInstallments": 60
     *       }
     *     }
     *   ]
     * }
     * 
     * @param array $sale Datos de la venta desde Logicware
     * @return array ['created' => bool, 'contract_id' => int|null, 'reason' => string|null]
     * @throws Exception
     */
    protected function processSale(array $sale): array
    {
        // Validar estructura mínima
        if (!isset($sale['documentNumber']) || !isset($sale['documents']) || empty($sale['documents'])) {
            return [
                'created' => false,
                'reason' => 'Venta sin DNI o sin documentos de venta'
            ];
        }

        // 1. Crear o actualizar cliente
        $client = $this->findOrCreateClientFromRealData($sale);

        $contractsCreated = 0;

        // 2. Procesar cada documento de venta (proforma/contrato)
        foreach ($sale['documents'] as $document) {
            
            // Solo procesar ventas completadas
            if (($document['status'] ?? '') !== 'Ventas') {
                continue;
            }

            // Procesar cada unidad vendida
            if (!isset($document['units']) || empty($document['units'])) {
                continue;
            }

            foreach ($document['units'] as $unit) {
                $unitNumber = $unit['unitNumber'] ?? null;

                if (!$unitNumber) {
                    Log::warning('[LogicwareImporter] Unit sin número', ['document' => $document['correlative'] ?? 'N/A']);
                    continue;
                }

                // 3. Buscar lote por código (external_code)
                $lot = Lot::where('external_code', $unitNumber)->first();

                if (!$lot) {
                    Log::warning('[LogicwareImporter] Lote no encontrado', [
                        'unit_number' => $unitNumber,
                        'document' => $document['correlative'] ?? 'N/A'
                    ]);
                    continue;
                }

                // 4. Vincular asesor (NO CREAR, solo vincular si existe)
                $advisor = $this->findAdvisorByName($document['seller'] ?? null);

                if (!$advisor) {
                    Log::warning('[LogicwareImporter] Asesor no encontrado', [
                        'seller_name' => $document['seller'] ?? 'N/A',
                        'document' => $document['correlative'] ?? 'N/A'
                    ]);
                    continue;
                }

                // 5. Verificar si ya existe un contrato para este lote
                $existingContract = Contract::where('lot_id', $lot->lot_id)->first();

                if ($existingContract) {
                    Log::info('[LogicwareImporter] Contrato ya existe para este lote', [
                        'lot_id' => $lot->lot_id,
                        'contract_id' => $existingContract->contract_id
                    ]);
                    continue;
                }

                // 6. Crear contrato
                $contract = $this->createContractFromRealData($client, $lot, $advisor, $document, $unit);
                
                // 7. Sincronizar cronograma de pagos desde Logicware (incluye estado de pagos)
                $correlative = $document['correlative'] ?? null;
                $scheduleCreated = false;
                
                if ($correlative) {
                    try {
                        Log::info('[LogicwareImporter] Intentando sincronizar cronograma desde Logicware', [
                            'contract_id' => $contract->contract_id,
                            'correlative' => $correlative
                        ]);
                        
                        $this->syncPaymentScheduleFromLogicware($contract, $correlative);
                        $scheduleCreated = true;
                        
                        Log::info('[LogicwareImporter] ✅ Cronograma sincronizado desde Logicware', [
                            'contract_id' => $contract->contract_id,
                            'correlative' => $correlative
                        ]);
                    } catch (Exception $scheduleError) {
                        Log::warning('[LogicwareImporter] ⚠️ No se pudo sincronizar cronograma desde Logicware', [
                            'contract_id' => $contract->contract_id,
                            'correlative' => $correlative,
                            'error' => $scheduleError->getMessage(),
                            'trace' => $scheduleError->getTraceAsString()
                        ]);
                        $scheduleCreated = false;
                    }
                }
                
                // Fallback: Si no se sincronizó desde Logicware, generar cronograma tradicional
                if (!$scheduleCreated) {
                    Log::info('[LogicwareImporter] 📅 Generando cronograma tradicional como fallback', [
                        'contract_id' => $contract->contract_id,
                        'has_correlative' => !empty($correlative)
                    ]);
                    
                    try {
                        $this->generatePaymentSchedules($contract, $document);
                        Log::info('[LogicwareImporter] ✅ Cronograma tradicional generado', [
                            'contract_id' => $contract->contract_id
                        ]);
                    } catch (Exception $e) {
                        Log::error('[LogicwareImporter] ❌ Error generando cronograma tradicional', [
                            'contract_id' => $contract->contract_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $contractsCreated++;
            }
        }

        if ($contractsCreated > 0) {
            return [
                'created' => true,
                'contracts_count' => $contractsCreated
            ];
        }

        return [
            'created' => false,
            'reason' => 'No se procesaron unidades válidas o los contratos ya existen'
        ];
    }

    /**
     * Buscar o crear cliente desde datos REALES de Logicware
     * 
     * @param array $clientData Datos del cliente
     * @return Client
     * @throws Exception
     */
    protected function findOrCreateClientFromRealData(array $clientData): Client
    {
        $documentNumber = $clientData['documentNumber'] ?? null;

        if (!$documentNumber) {
            throw new Exception('Cliente sin número de documento');
        }

        // Buscar cliente existente por doc_number
        $client = Client::where('doc_number', $documentNumber)->first();

        if ($client) {
            Log::info('[LogicwareImporter] Cliente existente encontrado', [
                'client_id' => $client->client_id,
                'doc_number' => $documentNumber
            ]);
            return $client;
        }

        // Crear nuevo cliente con todos los campos disponibles
        $birthDate = isset($clientData['birthDate']) ? substr($clientData['birthDate'], 0, 10) : null;
        
        $client = Client::create([
            'doc_type' => 'DNI',
            'doc_number' => $documentNumber,
            'first_name' => $clientData['firstName'] ?? 'Sin nombre',
            'last_name' => trim(($clientData['paternalSurname'] ?? '') . ' ' . ($clientData['maternalSurname'] ?? '')),
            'email' => $clientData['email'] ?? null,
            'primary_phone' => $clientData['phone'] ?? null,
            'type' => 'client',
            'date' => $birthDate,
            'source' => 'logicware'
        ]);

        // Crear dirección si existe
        if (!empty($clientData['address'])) {
            \Modules\CRM\Models\Address::create([
                'client_id' => $client->client_id,
                'line1' => $clientData['address'],
                'line2' => $clientData['district'] ?? null,
                'city' => $clientData['province'] ?? null,
                'state' => $clientData['department'] ?? null,
                'country' => 'PER'
            ]);
        }

        Log::info('[LogicwareImporter] Cliente creado', [
            'client_id' => $client->client_id,
            'doc_number' => $documentNumber
        ]);

        return $client;
    }

    /**
     * Buscar asesor por nombre completo con score-based matching
     * **NO CREA nuevos asesores**, solo vincula existentes
     * 
     * @param string|null $sellerName Nombre completo del vendedor desde Logicware
     * @return Employee|null
     */
    protected function findAdvisorByName(?string $sellerName): ?Employee
    {
        if (!$sellerName) {
            Log::warning('[LogicwareImporter] Seller sin nombre');
            return null;
        }

        // Limpiar nombre (quitar espacios extras)
        $sellerName = trim($sellerName);
        $sellerParts = array_filter(explode(' ', strtoupper($sellerName)));

        Log::info('[LogicwareImporter] 🔍 Buscando asesor', [
            'seller_from_api' => $sellerName,
            'seller_parts' => $sellerParts
        ]);

        // NO FILTRAR POR POSITION - el campo está NULL en BD
        // Logicware envía nombres cortos: "DAVID FEIJOO" = "FERNANDO DAVID FEIJOO GARCIA" en BD
        // Estrategia: seller viene como "NOMBRE APELLIDO" (2 palabras), buscar que ambas coincidan
        
        // Obtener todos los empleados y calcular score
        $allAdvisors = Employee::whereHas('user')->with('user')->get();
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
                        $score += 100; // Peso muy alto para coincidencia exacta
                        $foundExactMatch = true;
                        $matchedParts++;
                        break;
                    }
                    // Coincidencia como substring (menor peso)
                    elseif (stripos($advisorPart, $sellerPart) !== false) {
                        $score += 50; // Peso medio para substring
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
            
            // BONUS CRÍTICO: Si todas las partes del seller coinciden (2 de 2 palabras)
            if ($matchedParts === count($sellerParts)) {
                $score += 500; // Bonus masivo para coincidencia completa
            }
            
            Log::debug('[LogicwareImporter] Score calculado', [
                'advisor_name' => $advisorFullName,
                'advisor_id' => $advisor->employee_id,
                'score' => $score,
                'matched_parts' => $matchedParts,
                'total_seller_parts' => count($sellerParts)
            ]);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $advisor;
            }
        }

        // Requerir score mínimo para aceptar match
        if (!$bestMatch || $bestScore < 100) {
            Log::warning('[LogicwareImporter] ❌ Asesor NO encontrado (score insuficiente)', [
                'seller_name' => $sellerName,
                'best_score' => $bestScore,
                'required_score' => 100
            ]);
            return null;
        }

        Log::info('[LogicwareImporter] ✅ Asesor vinculado con score-based matching', [
            'employee_id' => $bestMatch->employee_id,
            'user_name' => ($bestMatch->user->first_name ?? '') . ' ' . ($bestMatch->user->last_name ?? ''),
            'seller_from_api' => $sellerName,
            'match_score' => $bestScore
        ]);

        return $bestMatch;
    }

    /**
     * Crear contrato desde datos REALES de Logicware
     * 
     * @param Client $client
     * @param Lot $lot
     * @param Employee $advisor
     * @param array $document Documento de venta (proforma)
     * @param array $unit Unidad vendida
     * @return Contract
     * @throws Exception
     */
    protected function createContractFromRealData(Client $client, Lot $lot, Employee $advisor, array $document, array $unit): Contract
    {
        $contractDate = isset($document['saleStartDate']) ? Carbon::parse($document['saleStartDate']) : now();
        
        $financing = $document['financing'] ?? [];
        
        // 🔍 DEBUG: Ver qué está llegando en el array unit
        Log::info('[LogicwareImporter] 🔍 DEBUG - Datos del unit', [
            'unit_complete' => $unit,
            'basePrice' => $unit['basePrice'] ?? 'NO EXISTE',
            'unitPrice' => $unit['unitPrice'] ?? 'NO EXISTE',
            'discount' => $unit['discount'] ?? 'NO EXISTE',
            'total' => $unit['total'] ?? 'NO EXISTE'
        ]);
        
        // Extraer TODOS los datos financieros completos
        $listPrice = $this->parseNumericValue($unit['listPrice'] ?? $unit['basePrice'] ?? 0); // Precio Base
        $unitPrice = $this->parseNumericValue($unit['unitPrice'] ?? $unit['price'] ?? 0); // Precio Unitario (Venta)
        $discount = $this->parseNumericValue($unit['discount'] ?? 0); // Descuento aplicado
        $totalPrice = $this->parseNumericValue($unit['total'] ?? $unit['totalPrice'] ?? 0); // Precio Total Final
        
        // Datos de financiamiento
        $downPayment = $this->parseNumericValue($financing['downPayment'] ?? 0);
        $amountToFinance = $this->parseNumericValue($financing['amountToFinance'] ?? 0);
        $totalInstallments = (int)($financing['totalInstallments'] ?? 0);
        $balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
        $bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);
        
        // Si no tenemos total_price, calcularlo desde listPrice - discount
        if (!$totalPrice && $listPrice) {
            $totalPrice = $listPrice - ($discount ?? 0);
        }
        
        $contract = Contract::create([
            'client_id' => $client->client_id,
            'lot_id' => $lot->lot_id,
            'advisor_id' => $advisor->employee_id,
            'contract_number' => $document['correlative'] ?? $this->generateContractNumber(),
            'sign_date' => $contractDate,  // ← CORREGIDO: era 'contract_date'
            'base_price' => $listPrice,  // Precio base del lote
            'unit_price' => $unitPrice,  // Precio unitario de venta
            'discount' => $discount,     // Descuento aplicado
            'total_price' => $totalPrice, // Precio total final
            'down_payment' => $downPayment,
            'financing_amount' => $amountToFinance,
            'term_months' => $totalInstallments,
            'balloon_payment' => $balloonPayment,
            'bpp' => $bppBonus,
            'currency' => $financing['currency'] ?? 'PEN',
            'status' => 'vigente',
            'interest_rate' => 0,
            'monthly_payment' => 0,
            'notes' => 'Importado desde Logicware el ' . now()->format('Y-m-d H:i:s')
        ]);
        
        Log::info('[LogicwareImporter] ✅ Contrato creado', [
            'contract_id' => $contract->contract_id,
            'contract_number' => $contract->contract_number,
            'client_id' => $client->client_id,
            'lot_id' => $lot->lot_id,
            'advisor_id' => $advisor->employee_id,
            'total_price' => $totalPrice,
            'discount' => $discount,
            'base_price' => $listPrice,
            'unit_price' => $unitPrice,
            'balloon_payment' => $balloonPayment,
            'bpp' => $bppBonus
        ]);

        return $contract;
    }
    
    /**
     * Parsear valor numérico (helper method)
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
     * Buscar o crear cliente desde datos de Logicware (MOCK structure - deprecated)
     * 
     * @param array $clientData Datos del cliente
     * @return Client
     * @throws Exception
     * @deprecated Usar findOrCreateClientFromRealData()
     */
    protected function findOrCreateClient(array $clientData): Client
    {
        $documentNumber = $clientData['documentNumber'] ?? null;

        if (!$documentNumber) {
            throw new Exception('Cliente sin número de documento');
        }

        // Buscar cliente existente por DNI
        $client = Client::where('dni', $documentNumber)->first();

        if ($client) {
            Log::info('[LogicwareImporter] Cliente existente encontrado', [
                'client_id' => $client->client_id,
                'dni' => $documentNumber
            ]);
            return $client;
        }

        // Crear nuevo cliente
        $client = Client::create([
            'dni' => $documentNumber,
            'first_name' => $clientData['name'] ?? 'Sin nombre',
            'last_name' => $clientData['lastName'] ?? 'Sin apellido',
            'email' => $clientData['email'] ?? null,
            'phone' => $clientData['phone'] ?? null,
            'client_type' => 'individual',
            'registration_date' => now()
        ]);

        Log::info('[LogicwareImporter] Cliente creado', [
            'client_id' => $client->client_id,
            'dni' => $documentNumber
        ]);

        return $client;
    }

    /**
     * Buscar asesor (vendedor) por employee_code
     * **NO CREA nuevos asesores**, solo vincula existentes
     * 
     * @param array $advisorData Datos del asesor desde Logicware
     * @return Employee|null
     */
    protected function findAdvisor(array $advisorData): ?Employee
    {
        $advisorCode = $advisorData['code'] ?? null;

        if (!$advisorCode) {
            Log::warning('[LogicwareImporter] Advisor sin código', ['advisor_data' => $advisorData]);
            return null;
        }

        // Buscar asesor por employee_code
        $advisor = Employee::where('employee_code', $advisorCode)
            ->where('position', 'Asesor')
            ->first();

        if (!$advisor) {
            Log::warning('[LogicwareImporter] Asesor no encontrado en BD', [
                'advisor_code' => $advisorCode,
                'advisor_name' => $advisorData['name'] ?? 'N/A'
            ]);
            return null;
        }

        Log::info('[LogicwareImporter] Asesor vinculado', [
            'employee_id' => $advisor->employee_id,
            'employee_code' => $advisorCode,
            'name' => $advisor->first_name . ' ' . $advisor->last_name
        ]);

        return $advisor;
    }

    /**
     * Crear contrato desde datos de Logicware
     * 
     * @param Client $client
     * @param Lot $lot
     * @param Employee $advisor
     * @param array $sale Datos completos de la venta
     * @param array $item Item específico de la venta
     * @return Contract
     * @throws Exception
     */
    protected function createContract(Client $client, Lot $lot, Employee $advisor, array $sale, array $item): Contract
    {
        $contractDate = isset($sale['saleDate']) ? Carbon::parse($sale['saleDate']) : now();

        $contract = Contract::create([
            'client_id' => $client->client_id,
            'lot_id' => $lot->lot_id,
            'advisor_id' => $advisor->employee_id,
            'contract_number' => $sale['documentNumber'] ?? $this->generateContractNumber(),
            'contract_date' => $contractDate,
            'total_price' => $item['totalPrice'] ?? 0,
            'down_payment' => $item['downPayment'] ?? 0,
            'financing_amount' => $item['financedAmount'] ?? 0,
            'term_months' => $item['installments'] ?? 0,
            'monthly_payment' => $item['monthlyPayment'] ?? 0,
            'currency' => $item['currency'] ?? 'PEN',
            'status' => 'vigente',
            'interest_rate' => 0,
            'notes' => 'Importado desde Logicware'
        ]);

        Log::info('[LogicwareImporter] ✅ Contrato creado', [
            'contract_id' => $contract->contract_id,
            'contract_number' => $contract->contract_number,
            'client_id' => $client->client_id,
            'lot_id' => $lot->lot_id,
            'advisor_id' => $advisor->employee_id
        ]);

        return $contract;
    }

    /**
     * Generar cronogramas de pago para el contrato
     * 
     * @param Contract $contract
     * @param array $document Datos del documento de Logicware
     * @return void
     * @throws Exception
     */
    protected function generatePaymentSchedules(Contract $contract, array $document): void
    {
        try {
            $financing = $document['financing'] ?? [];
            
            // Extraer todos los datos de financiamiento
            $totalInstallments = (int)($financing['totalInstallments'] ?? 0);
            $initialInstallments = (int)($financing['initialInstallments'] ?? 1);
            $financingInstallments = (int)($financing['financingInstallments'] ?? 0);
            $downPayment = $this->parseNumericValue($financing['downPayment'] ?? 0);
            $amountToFinance = $this->parseNumericValue($financing['amountToFinance'] ?? 0);
            $balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
            $bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);
            
            // CRÍTICO: Usar fecha de venta desde Logicware, NO fecha actual
            $saleDate = isset($document['saleStartDate']) 
                ? Carbon::parse($document['saleStartDate'])
                : now();
            
            Log::info('[LogicwareImporter] 📅 Generando cronogramas completos', [
                'contract_id' => $contract->contract_id,
                'sale_date' => $saleDate->format('Y-m-d'),
                'total_installments' => $totalInstallments,
                'balloon' => $balloonPayment > 0 ? 'Sí' : 'No',
                'bpp' => $bppBonus > 0 ? 'Sí' : 'No'
            ]);
            
            if ($totalInstallments <= 0) {
                Log::warning('[LogicwareImporter] No se generan cuotas, totalInstallments es 0');
                return;
            }
            
            $installmentNumber = 1;
            
            // 1. Crear cuota(s) inicial(es)
            if ($downPayment > 0 && $initialInstallments > 0) {
                $initialPaymentAmount = $downPayment / $initialInstallments;
                
                for ($i = 0; $i < $initialInstallments; $i++) {
                    PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber++,
                        'due_date' => $saleDate->copy()->addMonths($i)->toDateString(),
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
                    PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber++,
                        'due_date' => $saleDate->copy()->addMonths($initialInstallments + $i)->toDateString(),
                        'amount' => $monthlyPayment,
                        'status' => 'pendiente',
                        'type' => 'financiamiento',
                        'currency' => $contract->currency
                    ]);
                }
            }
            
            // 3. 🔥 Crear cuota BALÓN (si existe)
            if ($balloonPayment > 0) {
                PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $installmentNumber++,
                    'due_date' => $saleDate->copy()->addMonths($initialInstallments + $financingInstallments)->toDateString(),
                    'amount' => $balloonPayment,
                    'status' => 'pendiente',
                    'type' => 'balon',
                    'currency' => $contract->currency,
                    'notes' => 'Cuota Balón'
                ]);
            }
            
            // 4. 🔥 Crear cuota BONO BPP (si existe)
            if ($bppBonus > 0) {
                PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'installment_number' => $installmentNumber++,
                    'due_date' => $saleDate->copy()->addMonths($initialInstallments + $financingInstallments + 1)->toDateString(),
                    'amount' => $bppBonus,
                    'status' => 'pendiente',
                    'type' => 'bono_bpp',
                    'currency' => $contract->currency,
                    'notes' => 'Bono Buen Pagador'
                ]);
            }
            
            Log::info('[LogicwareImporter] ✅ Cronogramas completos generados', [
                'contract_id' => $contract->contract_id,
                'total_cuotas' => $installmentNumber - 1,
                'start_date' => $saleDate->format('Y-m-d')
            ]);
            
        } catch (Exception $e) {
            Log::error('[LogicwareImporter] Error generando cronogramas', [
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sincronizar cronograma de pagos desde Logicware
     * 
     * Este método reemplaza el método tradicional. Consulta el endpoint
     * /external/payment-schedules/{correlative} para obtener:
     * - Todas las cuotas (iniciales, financiamiento, balón, BPP)
     * - Estado de pago de cada cuota
     * - Fechas de vencimiento exactas
     * 
     * @param Contract $contract
     * @param string $correlative Número correlativo de la proforma
     * @return void
     * @throws Exception
     */
    protected function syncPaymentScheduleFromLogicware(Contract $contract, string $correlative): void
    {
        try {
            Log::info('[LogicwareImporter] 🔄 Sincronizando cronograma desde Logicware', [
                'contract_id' => $contract->contract_id,
                'correlative' => $correlative
            ]);

            // Obtener cronograma completo desde Logicware
            $scheduleData = $this->logicwareService->getPaymentSchedule($correlative);

            if (!isset($scheduleData['data']['installments']) || !is_array($scheduleData['data']['installments'])) {
                Log::warning('[LogicwareImporter] No se recibieron cuotas desde Logicware', [
                    'correlative' => $correlative,
                    'structure' => array_keys($scheduleData['data'] ?? [])
                ]);
                return;
            }

            $installments = $scheduleData['data']['installments'];
            $totalInstallments = count($installments);

            Log::info('[LogicwareImporter] Cuotas recibidas desde Logicware', [
                'total' => $totalInstallments
            ]);

            $createdCount = 0;
            $paidCount = 0;

            foreach ($installments as $inst) {
                // Determinar el tipo de cuota por el label
                $label = strtolower($inst['label'] ?? '');
                $type = 'otro'; // Por defecto

                if (strpos($label, 'inicial') !== false || strpos($label, 'separación') !== false || strpos($label, 'reserva') !== false) {
                    $type = 'inicial';
                } elseif (strpos($label, 'balon') !== false || strpos($label, 'balón') !== false) {
                    $type = 'balon';
                } elseif (strpos($label, 'pagador') !== false || strpos($label, 'bpp') !== false || strpos($label, 'buen pagador') !== false) {
                    $type = 'bono_bpp';
                } elseif (strpos($label, 'financiar') !== false || strpos($label, 'cuota') !== false) {
                    $type = 'financiamiento';
                }

                // Determinar estado: si ya pagó algo, está pagada
                $totalPaid = $this->parseNumericValue($inst['totalPaidAmount'] ?? 0);
                $payment = $this->parseNumericValue($inst['payment'] ?? 0);
                $remainingBalance = $this->parseNumericValue($inst['remainingBalance'] ?? $payment);
                
                $isPaid = $totalPaid >= $payment || $remainingBalance == 0 || strtoupper($inst['status'] ?? '') === 'PAID';
                $status = $isPaid ? 'pagado' : 'pendiente';

                if ($isPaid) {
                    $paidCount++;
                }

                // Crear la cuota en nuestro sistema
                \Modules\Sales\Models\PaymentSchedule::create([
                    'contract_id' => $contract->contract_id,
                    'installment_number' => (int)($inst['installmentNumber'] ?? 0),
                    'due_date' => $inst['dueDate'] ? Carbon::parse($inst['dueDate'])->toDateString() : null,
                    'amount' => $payment,
                    'status' => $status,
                    'type' => $type,
                    'currency' => $contract->currency ?? 'PEN',
                    'notes' => $inst['label'] ?? null,
                    'logicware_schedule_det_id' => $inst['scheduleDetId'] ?? null,
                    'logicware_paid_amount' => $totalPaid > 0 ? $totalPaid : null,
                    'paid_date' => ($isPaid && isset($inst['paymentDate'])) ? Carbon::parse($inst['paymentDate'])->toDateString() : null
                ]);

                $createdCount++;
            }

            Log::info('[LogicwareImporter] ✅ Cronograma sincronizado desde Logicware', [
                'contract_id' => $contract->contract_id,
                'correlative' => $correlative,
                'total_cuotas' => $createdCount,
                'cuotas_pagadas' => $paidCount,
                'cuotas_pendientes' => $createdCount - $paidCount
            ]);

        } catch (Exception $e) {
            Log::error('[LogicwareImporter] Error sincronizando cronograma desde Logicware', [
                'contract_id' => $contract->contract_id,
                'correlative' => $correlative,
                'error' => $e->getMessage()
            ]);
            // No lanzar excepción, permitir que el contrato se cree sin cronograma
        }
    }

    /**
     * Generar número de contrato automático
     * 
     * @return string
     */
    protected function generateContractNumber(): string
    {
        $lastContract = Contract::orderBy('contract_id', 'desc')->first();
        $nextNumber = $lastContract ? ($lastContract->contract_id + 1) : 1;
        return 'LGW-' . now()->format('Ym') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
