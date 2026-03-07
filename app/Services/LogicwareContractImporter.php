<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\Sales\Models\Payment;
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
     * Importar una sola venta desde el payload del webhook
     * 
     * @param array $saleData Datos de la venta desde el webhook
     * @return Contract
     * @throws Exception
     */
    public function importSingleSale(array $saleData): Contract
    {
        try {
            Log::info('[LogicwareImporter] 📦 Importando venta individual desde webhook', [
                'document_number' => $saleData['documentNumber'] ?? 'N/A'
            ]);

            DB::beginTransaction();

            $result = $this->processSale($saleData);
            
            if (!$result['created'] && isset($result['contract'])) {
                // Contrato ya existía
                Log::info('[LogicwareImporter] Contrato ya existe, retornando existente', [
                    'contract_id' => $result['contract']->contract_id
                ]);
                DB::commit();
                return $result['contract'];
            }
            
            if (!isset($result['contract'])) {
                throw new Exception('No se pudo procesar la venta: ' . ($result['reason'] ?? 'Error desconocido'));
            }

            DB::commit();

            Log::info('[LogicwareImporter] ✅ Venta individual importada', [
                'contract_id' => $result['contract']->contract_id,
                'contract_number' => $result['contract']->contract_number
            ]);

            return $result['contract'];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[LogicwareImporter] Error importando venta individual', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
            
            $docStatus = $document['status'] ?? '';
            $statusLower = strtolower(trim($docStatus));
            $correlative = $document['correlative'] ?? '';

            if (str_starts_with($correlative, 'Cot. Nro.')) {
                Log::info('[LogicwareImporter] ⏭️ Saltando documento (es solo cotización)', [
                    'correlative' => $correlative
                ]);
                continue;
            }

            // Identificar si es venta o reserva basado en el ESTADO
            $isContract = in_array($statusLower, ['venta', 'ventas', 'vendido']);
            $isReservation = in_array($statusLower, ['en proceso venta', 'en proceso separación', 'en proceso separacion', 'separacion', 'separación']);
            
            // Si el correlativo dice explícitamente "Sep. Nro." o "Res Nro.", forzar como reserva
            if (str_starts_with($correlative, 'Sep. Nro.') || str_starts_with($correlative, 'Res Nro.')) {
                $isReservation = true;
                $isContract = false;
            }

            if (!$isContract && !$isReservation) {
                Log::info('[LogicwareImporter] ⏭️ Saltando documento (estado no válido: ' . $docStatus . ')', [
                    'correlative' => $correlative
                ]);
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
                    Log::warning('[LogicwareImporter] ⚠️ Asesor no encontrado - contrato se creará sin asesor', [
                        'seller_name' => $document['seller'] ?? 'N/A',
                        'document' => $document['correlative'] ?? 'N/A'
                    ]);
                    // NO hacer continue — crear contrato/reserva con advisor_id = NULL
                }

                // 5. Verificar si es reserva o contrato
                if ($isReservation) {
                    $this->createOrUpdateReservation($client, $lot, $advisor, $document, $unit);
                    continue; // Se procesó como reserva, pasamos a la siguiente unidad/documento
                }

                // 6. Verificar si ya existe un contrato para este lote
                $existingContract = Contract::where('lot_id', $lot->lot_id)->first();

                if ($existingContract) {
                    Log::info('[LogicwareImporter] 🔄 Contrato existente - actualizando', [
                        'lot_id' => $lot->lot_id,
                        'contract_id' => $existingContract->contract_id
                    ]);
                    
                    // Actualizar datos del contrato existente
                    $contract = $this->updateContractFromRealData($existingContract, $client, $lot, $advisor, $document, $unit);
                } else {
                    // Crear nuevo contrato
                    $contract = $this->createContractFromRealData($client, $lot, $advisor, $document, $unit);
                }
                
                // 7. Sincronizar cronograma de pagos desde Logicware (merge inteligente)
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
                
                // 8. Inferir actual_sale_date desde el primer pago del cronograma
                // Esto permite que las comisiones se asignen al período correcto
                try {
                    $firstPayment = $contract->paymentSchedules()
                                             ->orderBy('due_date', 'asc')
                                             ->first();
                    
                    if ($firstPayment && $firstPayment->due_date) {
                        $actualSaleDate = Carbon::parse($firstPayment->due_date)->toDateString();
                        $contract->update(['actual_sale_date' => $actualSaleDate]);
                        
                        Log::info('[LogicwareImporter] 📅 actual_sale_date inferida del primer pago', [
                            'contract_id' => $contract->contract_id,
                            'sign_date' => $contract->sign_date?->toDateString(),
                            'actual_sale_date' => $actualSaleDate,
                            'differs' => $contract->sign_date?->toDateString() !== $actualSaleDate
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('[LogicwareImporter] ⚠️ No se pudo inferir actual_sale_date', [
                        'contract_id' => $contract->contract_id,
                        'error' => $e->getMessage()
                    ]);
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
    protected function createContractFromRealData(Client $client, Lot $lot, ?Employee $advisor, array $document, array $unit): Contract
    {
        $contractDate = isset($document['saleStartDate']) ? Carbon::parse($document['saleStartDate']) : now();
        
        $docStatus = strtolower(trim($document['status'] ?? ''));
        $docResolution = strtolower(trim($document['resolutionStatus'] ?? $document['resolution_status'] ?? ''));
        $unitStatus = strtolower(trim($unit['status'] ?? $unit['state'] ?? ''));
        
        $resolvedStatuses = ['resuelto', 'cancelado', 'anulado'];
        $isResolved = in_array($docStatus, $resolvedStatuses) || in_array($docResolution, $resolvedStatuses) || in_array($unitStatus, $resolvedStatuses);
        $contractStatus = $isResolved ? 'resuelto' : 'vigente';

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
            'advisor_id' => $advisor?->employee_id,
            'contract_number' => $document['correlative'] ?? $this->generateContractNumber(),
            'contract_date' => $contractDate,
            'sign_date' => $contractDate,
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
            'status' => $contractStatus,
            'interest_rate' => 0,
            'monthly_payment' => 0,
            'notes' => 'Importado desde Logicware el ' . now()->format('Y-m-d H:i:s')
        ]);
        
        Log::info('[LogicwareImporter] ✅ Contrato creado', [
            'contract_id' => $contract->contract_id,
            'contract_number' => $contract->contract_number,
            'client_id' => $client->client_id,
            'lot_id' => $lot->lot_id,
            'advisor_id' => $advisor?->employee_id,
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
     * Actualizar contrato existente con datos de Logicware
     * 
     * @param Contract $contract Contrato existente
     * @param Client $client
     * @param Lot $lot
     * @param Employee $advisor
     * @param array $document Documento de Logicware
     * @param array $unit Unidad vendida
     * @return Contract
     */
    protected function updateContractFromRealData(Contract $contract, Client $client, Lot $lot, ?Employee $advisor, array $document, array $unit): Contract
    {
        $contractDate = isset($document['saleStartDate']) ? Carbon::parse($document['saleStartDate']) : $contract->sign_date;
        
        $docStatus = strtolower(trim($document['status'] ?? ''));
        $docResolution = strtolower(trim($document['resolutionStatus'] ?? $document['resolution_status'] ?? ''));
        $unitStatus = strtolower(trim($unit['status'] ?? $unit['state'] ?? ''));
        
        $resolvedStatuses = ['resuelto', 'cancelado', 'anulado'];
        $isResolved = in_array($docStatus, $resolvedStatuses) || in_array($docResolution, $resolvedStatuses) || in_array($unitStatus, $resolvedStatuses);
        $contractStatus = $isResolved ? 'resuelto' : 'vigente';

        $financing = $document['financing'] ?? [];
        
        // Extraer datos financieros actualizados
        $listPrice = $this->parseNumericValue($unit['listPrice'] ?? $unit['basePrice'] ?? 0);
        $unitPrice = $this->parseNumericValue($unit['unitPrice'] ?? $unit['price'] ?? 0);
        $discount = $this->parseNumericValue($unit['discount'] ?? 0);
        $totalPrice = $this->parseNumericValue($unit['total'] ?? $unit['totalPrice'] ?? 0);
        
        $downPayment = $this->parseNumericValue($financing['downPayment'] ?? 0);
        $amountToFinance = $this->parseNumericValue($financing['amountToFinance'] ?? 0);
        $totalInstallments = (int)($financing['totalInstallments'] ?? 0);
        $balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
        $bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);
        
        if (!$totalPrice && $listPrice) {
            $totalPrice = $listPrice - ($discount ?? 0);
        }
        
        // Actualizar datos del contrato
        $contract->update([
            'client_id' => $client->client_id,
            'advisor_id' => $advisor?->employee_id,
            'contract_number' => $document['correlative'] ?? $contract->contract_number,
            'contract_date' => $contractDate,
            'sign_date' => $contractDate,
            'base_price' => $listPrice,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total_price' => $totalPrice,
            'down_payment' => $downPayment,
            'financing_amount' => $amountToFinance,
            'term_months' => $totalInstallments,
            'balloon_payment' => $balloonPayment,
            'bpp' => $bppBonus,
            'currency' => $financing['currency'] ?? $contract->currency ?? 'PEN',
            'status' => $contractStatus,
            'notes' => ($contract->notes ?? '') . "\nActualizado desde Logicware el " . now()->format('Y-m-d H:i:s')
        ]);
        
        Log::info('[LogicwareImporter] ✅ Contrato actualizado', [
            'contract_id' => $contract->contract_id,
            'contract_number' => $contract->contract_number,
            'total_price' => $totalPrice,
            'discount' => $discount,
            'balloon_payment' => $balloonPayment,
            'bpp' => $bppBonus
        ]);

        return $contract;
    }

    /**
     * Procesar documento de separación o reserva en Logicware
     */
    protected function createOrUpdateReservation(Client $client, Lot $lot, ?Employee $advisor, array $document, array $unit): void
    {
        $reservationDate = isset($document['saleStartDate']) 
            ? Carbon::parse($document['saleStartDate'])->format('Y-m-d')
            : (isset($document['saleDate']) ? Carbon::parse($document['saleDate'])->format('Y-m-d') : now()->format('Y-m-d'));

        $financing = $document['financing'] ?? [];
        $reservationAmount = $this->parseNumericValue($financing['reservationAmount'] ?? $financing['downPayment'] ?? $unit['basePrice'] ?? 0); 
        
        if ($reservationAmount == 0) {
            $reservationAmount = $this->parseNumericValue($unit['total'] ?? $unit['totalPrice'] ?? $unit['unitPrice'] ?? 0);
        }

        $reservation = \Modules\Sales\Models\Reservation::where('lot_id', $lot->lot_id)
            ->where('client_id', $client->client_id)
            ->first();

        $status = 'activa';
        $docStatus = strtolower(trim($document['status'] ?? ''));
        $docResolution = strtolower(trim($document['resolutionStatus'] ?? $document['resolution_status'] ?? ''));
        $unitStatus = strtolower(trim($unit['status'] ?? $unit['state'] ?? ''));
        
        $resolvedStatuses = ['resuelto', 'cancelado', 'anulado'];
        if (in_array($docStatus, $resolvedStatuses) || in_array($docResolution, $resolvedStatuses) || in_array($unitStatus, $resolvedStatuses)) {
            $status = 'anulada';
        }

        if ($reservation) {
            $reservation->update([
                'advisor_id' => $advisor?->employee_id,
                'reservation_date' => $reservationDate,
                'deposit_amount' => $reservationAmount,
                'status' => $status,
            ]);
            Log::info('[LogicwareImporter] 🔄 Reserva actualizada', ['reservation_id' => $reservation->reservation_id, 'lot_id' => $lot->lot_id, 'status' => $status]);
        } else {
            $reservation = \Modules\Sales\Models\Reservation::create([
                'lot_id' => $lot->lot_id,
                'client_id' => $client->client_id,
                'advisor_id' => $advisor?->employee_id,
                'reservation_date' => $reservationDate,
                'expiration_date' => Carbon::parse($reservationDate)->addDays(30)->format('Y-m-d'),
                'deposit_amount' => $reservationAmount,
                'status' => $status,
            ]);
            Log::info('[LogicwareImporter] 🏷️ Reserva creada', ['reservation_id' => $reservation->reservation_id, 'lot_id' => $lot->lot_id, 'status' => $status]);
        }

        // Si la reserva está activa y el lote no estaba vendido, se marca como reservado
        if ($status === 'activa' && $lot->status !== 'vendido') {
            $lot->update(['status' => 'reservado']);
        } elseif ($status === 'anulada' && $lot->status === 'reservado') {
            // Si la reserva se anula y el lote sólo estaba reservado, se libera
            $lot->update(['status' => 'disponible']);
        }
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
    public function syncPaymentScheduleFromLogicware(Contract $contract, string $correlative): array
    {
        try {
            Log::info('[LogicwareImporter] 🔄 Sincronizando cronograma desde Logicware (merge inteligente)', [
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
                return [
                    'success' => false,
                    'message' => 'No se recibieron cuotas desde Logicware',
                    'total_installments' => 0
                ];
            }

            $installments = $scheduleData['data']['installments'];
            $totalInstallments = count($installments);

            Log::info('[LogicwareImporter] Cuotas recibidas desde Logicware', [
                'total' => $totalInstallments
            ]);

            // Obtener cuotas existentes en nuestro sistema
            $existingSchedules = \Modules\Sales\Models\PaymentSchedule::where('contract_id', $contract->contract_id)
                ->get()
                ->keyBy('installment_number');

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $paidCount = 0;
            $paymentsCreatedCount = 0;

            foreach ($installments as $inst) {
                $installmentNumber = (int)($inst['installmentNumber'] ?? 0);
                
                // Determinar el tipo de cuota por el label
                $label = strtolower($inst['label'] ?? '');
                $type = 'otro';

                if (strpos($label, 'inicial') !== false || strpos($label, 'separación') !== false || strpos($label, 'reserva') !== false) {
                    $type = 'inicial';
                } elseif (strpos($label, 'balon') !== false || strpos($label, 'balón') !== false) {
                    $type = 'balon';
                } elseif (strpos($label, 'pagador') !== false || strpos($label, 'bpp') !== false || strpos($label, 'buen pagador') !== false) {
                    $type = 'bono_bpp';
                } elseif (strpos($label, 'financiar') !== false || strpos($label, 'cuota') !== false) {
                    $type = 'financiamiento';
                }

                // Determinar estado desde Logicware
                $totalPaid = $this->parseNumericValue($inst['totalPaidAmount'] ?? 0);
                $payment = $this->parseNumericValue($inst['payment'] ?? 0);
                $remainingBalance = $this->parseNumericValue($inst['remainingBalance'] ?? $payment);
                
                $isPaid = $totalPaid >= $payment || $remainingBalance == 0 || strtoupper($inst['status'] ?? '') === 'PAID';
                $logicwareStatus = $isPaid ? 'pagado' : 'pendiente';

                if ($isPaid) {
                    $paidCount++;
                }

                $dueDate = $inst['dueDate'] ? Carbon::parse($inst['dueDate'])->toDateString() : null;
                $paidDate = ($isPaid && isset($inst['paymentDate'])) ? Carbon::parse($inst['paymentDate'])->toDateString() : null;

                // Verificar si la cuota ya existe
                if (isset($existingSchedules[$installmentNumber])) {
                    $existingSchedule = $existingSchedules[$installmentNumber];
                    
                    // MERGE INTELIGENTE: Priorizar pagos locales
                    // Si localmente está pagado, NO sobrescribir con estado de Logicware
                    $finalStatus = $existingSchedule->status === 'pagado' ? 'pagado' : $logicwareStatus;
                    $finalPaidDate = $existingSchedule->paid_date ?? $paidDate;
                    $finalPaidAmount = $existingSchedule->amount_paid ?? ($isPaid ? ($totalPaid > 0 ? $totalPaid : $payment) : null);
                    
                    // Actualizar datos de la cuota (montos, fechas) pero respetar pagos locales
                    $existingSchedule->update([
                        'due_date' => $dueDate,
                        'amount' => $payment,
                        'amount_paid' => $finalStatus === 'pagado' ? ($finalPaidAmount ?? $payment) : $existingSchedule->amount_paid,
                        'status' => $finalStatus,
                        'type' => $type,
                        'notes' => $inst['label'] ?? $existingSchedule->notes,
                        'logicware_schedule_det_id' => $inst['scheduleDetId'] ?? null,
                        'logicware_paid_amount' => $isPaid ? ($totalPaid > 0 ? $totalPaid : $payment) : null,
                        'paid_date' => $finalPaidDate
                    ]);
                    
                    // Si la cuota está pagada, crear registro de pago si no existe
                    if ($finalStatus === 'pagado') {
                        if ($this->createPaymentForSchedule($existingSchedule, $contract, $totalPaid, $finalPaidDate)) {
                            $paymentsCreatedCount++;
                        }
                    }
                    
                    $updatedCount++;
                    
                    Log::debug('[LogicwareImporter] Cuota actualizada', [
                        'installment_number' => $installmentNumber,
                        'status' => $finalStatus,
                        'local_was_paid' => $existingSchedule->status === 'pagado',
                        'logicware_status' => $logicwareStatus
                    ]);
                } else {
                    // Crear nueva cuota
                    $newSchedule = \Modules\Sales\Models\PaymentSchedule::create([
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber,
                        'due_date' => $dueDate,
                        'amount' => $payment,
                        'amount_paid' => $isPaid ? ($totalPaid > 0 ? $totalPaid : $payment) : null,
                        'status' => $logicwareStatus,
                        'type' => $type,
                        'currency' => $contract->currency ?? 'PEN',
                        'notes' => $inst['label'] ?? null,
                        'logicware_schedule_det_id' => $inst['scheduleDetId'] ?? null,
                        'logicware_paid_amount' => $totalPaid > 0 ? $totalPaid : ($isPaid ? $payment : null),
                        'paid_date' => $paidDate
                    ]);

                    // Si la cuota viene pagada desde Logicware, crear registro de pago
                    if ($isPaid) {
                        $paymentAmount = $totalPaid > 0 ? $totalPaid : $payment;
                        if ($this->createPaymentForSchedule($newSchedule, $contract, $paymentAmount, $paidDate)) {
                            $paymentsCreatedCount++;
                        }
                    }

                    $createdCount++;
                }
            }

            Log::info('[LogicwareImporter] ✅ Cronograma sincronizado con merge inteligente', [
                'contract_id' => $contract->contract_id,
                'correlative' => $correlative,
                'cuotas_creadas' => $createdCount,
                'cuotas_actualizadas' => $updatedCount,
                'cuotas_pagadas' => $paidCount,
                'pagos_creados' => $paymentsCreatedCount,
                'total_procesadas' => $createdCount + $updatedCount
            ]);

            return [
                'success' => true,
                'message' => 'Cronograma sincronizado exitosamente',
                'total_installments' => $totalInstallments,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'paid' => $paidCount,
                'payments_created' => $paymentsCreatedCount ?? 0,
                'skipped' => $skippedCount
            ];

        } catch (Exception $e) {
            Log::error('[LogicwareImporter] Error sincronizando cronograma desde Logicware', [
                'contract_id' => $contract->contract_id,
                'correlative' => $correlative,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error sincronizando cronograma: ' . $e->getMessage(),
                'total_installments' => 0
            ];
        }
    }

    /**
     * Crear registro de pago para una cuota pagada (importación/sincronización)
     * 
     * Verifica que no exista ya un pago para evitar duplicados.
     * El modelo Payment dispara automáticamente syncWithCollections()
     * y addPaymentRecordToCurrentCut() en su evento boot::created.
     *
     * @param \Modules\Sales\Models\PaymentSchedule $schedule
     * @param Contract $contract
     * @param float $amount
     * @param string|null $paidDate
     * @return bool True si se creó el pago, false si ya existía
     */
    protected function createPaymentForSchedule($schedule, Contract $contract, float $amount, ?string $paidDate): bool
    {
        try {
            // Verificar que no exista ya un pago para esta cuota
            $existingPayment = Payment::where('schedule_id', $schedule->schedule_id)
                ->where('contract_id', $contract->contract_id)
                ->first();

            if ($existingPayment) {
                Log::debug('[LogicwareImporter] Pago ya existe para cuota, omitiendo', [
                    'schedule_id' => $schedule->schedule_id,
                    'payment_id' => $existingPayment->payment_id
                ]);
                return false;
            }

            $paymentAmount = $amount > 0 ? $amount : $schedule->amount;
            $paymentDate = $paidDate ?? $schedule->due_date ?? now()->toDateString();

            Payment::create([
                'schedule_id' => $schedule->schedule_id,
                'contract_id' => $contract->contract_id,
                'payment_date' => $paymentDate,
                'amount' => $paymentAmount,
                'method' => 'importacion_logicware',
                'reference' => 'LGW-SYNC-' . $contract->contract_number . '-C' . $schedule->installment_number
            ]);

            Log::info('[LogicwareImporter] ✅ Pago creado para cuota pagada', [
                'schedule_id' => $schedule->schedule_id,
                'contract_id' => $contract->contract_id,
                'installment' => $schedule->installment_number,
                'amount' => $paymentAmount,
                'date' => $paymentDate
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('[LogicwareImporter] Error creando pago para cuota', [
                'schedule_id' => $schedule->schedule_id,
                'contract_id' => $contract->contract_id,
                'error' => $e->getMessage()
            ]);
            return false;
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
