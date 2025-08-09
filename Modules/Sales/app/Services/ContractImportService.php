<?php

namespace Modules\Sales\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\Address;
use Modules\HumanResources\Models\Employee;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class ContractImportService
{
    private array $errors = [];
    private array $processed = [];
    private int $successCount = 0;
    private int $errorCount = 0;

    /**
     * Normalizar texto removiendo acentos y caracteres especiales
     * para hacer búsquedas insensibles a tildes
     */
    private function normalizeText(string $text): string
    {
        // Convertir a minúsculas
        $text = strtolower(trim($text));
        
        // Mapeo de caracteres con acentos a sin acentos (incluye mayúsculas y minúsculas)
        $accents = [
            // Minúsculas
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ā' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'ō' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            // Mayúsculas
            'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ā' => 'a', 'Ã' => 'a',
            'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e', 'Ē' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i', 'Ī' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Ō' => 'o', 'Õ' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u', 'Ū' => 'u',
            'Ñ' => 'n', 'Ç' => 'c'
        ];
        
        // Reemplazar caracteres con acentos
        $text = strtr($text, $accents);
        
        // Remover caracteres especiales adicionales
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Normalizar espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Procesar archivo Excel de contratos/reservaciones
     */
    public function processExcel(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                throw new Exception('El archivo está vacío');
            }

            $headers = array_shift($rows);
            $validation = $this->validateExcelStructure($headers);
            
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }

            $headerMap = $this->mapHeaders($headers);

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos desde la fila 2 (después del header)
                
                try {
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $data = $this->mapRowData($row, $headerMap);
                    $this->processRow($data, $rowNumber);
                    $this->successCount++;
                    
                } catch (Exception $e) {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                    Log::error("Error procesando fila {$rowNumber}: " . $e->getMessage());
                }
            }

            DB::commit();

            return [
                'success' => true,
                'processed' => $this->successCount,
                'errors' => $this->errorCount,
                'error_details' => $this->errors,
                'message' => "Procesadas {$this->successCount} filas exitosamente, {$this->errorCount} errores"
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en importación de contratos: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processed' => $this->successCount,
                'errors' => $this->errorCount,
                'error_details' => $this->errors
            ];
        }
    }

    /**
     * Validar estructura del archivo Excel - Actualizado para template integral
     */
    public function validateExcelStructure(array $headers): array
    {
        $requiredHeaders = [
            'ASESOR_NOMBRE', 'CLIENTE_NOMBRE_COMPLETO', 
            'LOTE_NUMERO', 'FECHA_VENTA'
        ];
        
        $missingHeaders = [];
        
        foreach ($requiredHeaders as $required) {
            $found = false;
            foreach ($headers as $header) {
                if (trim(strtoupper($header)) === $required) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $missingHeaders[] = $required;
            }
        }
        
        if (empty($missingHeaders)) {
            return ['valid' => true];
        }
        
        return [
            'valid' => false,
            'error' => 'Faltan las siguientes columnas requeridas: ' . implode(', ', $missingHeaders)
        ];
    }

    /**
     * Mapear headers del Excel a índices - Actualizado para template integral
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = trim(strtoupper($header));
            
            // Mapeo de headers del template integral actual
            switch ($header) {
                // Sección Asesor
                case 'ASESOR_NOMBRE':
                    $map['advisor_name'] = $index;
                    break;
                case 'ASESOR_CODIGO':
                    $map['advisor_code'] = $index;
                    break;
                case 'ASESOR_EMAIL':
                    $map['advisor_email'] = $index;
                    break;
                case 'ASESOR_TELEFONO':
                    $map['advisor_phone'] = $index;
                    break;
                
                // Sección Venta
                case 'FECHA_VENTA':
                    $map['sale_date'] = $index;
                    break;
                case 'CANAL_VENTA':
                    $map['sale_channel'] = $index;
                    break;
                case 'CAMPANA':
                    $map['campaign'] = $index;
                    break;
                
                // Sección Cliente
                case 'CLIENTE_NOMBRES':
                    $map['client_first_name'] = $index;
                    break;
                case 'CLIENTE_APELLIDOS':
                    $map['client_last_name'] = $index;
                    break;
                case 'CLIENTE_NOMBRE_COMPLETO':
                    $map['client_full_name'] = $index;
                    break;
                case 'CLIENTE_TIPO_DOCUMENTO':
                    $map['client_doc_type'] = $index;
                    break;
                case 'CLIENTE_NUMERO_DOCUMENTO':
                    $map['client_doc_number'] = $index;
                    break;
                case 'CLIENTE_EMAIL':
                    $map['client_email'] = $index;
                    break;
                case 'CLIENTE_TELEFONO_1':
                    $map['client_phone1'] = $index;
                    break;
                case 'CLIENTE_TELEFONO_2':
                    $map['client_phone2'] = $index;
                    break;
                case 'CLIENTE_FECHA_NACIMIENTO':
                    $map['client_birth_date'] = $index;
                    break;
                case 'CLIENTE_ESTADO_CIVIL':
                    $map['client_marital_status'] = $index;
                    break;
                case 'CLIENTE_OCUPACION':
                    $map['client_occupation'] = $index;
                    break;
                case 'CLIENTE_SALARIO':
                    $map['client_salary'] = $index;
                    break;
                case 'CLIENTE_TIPO':
                    $map['client_type'] = $index;
                    break;
                case 'CLIENTE_OBSERVACIONES':
                    $map['client_observations'] = $index;
                    break;
                
                // Sección Dirección Cliente
                case 'CLIENTE_DIRECCION':
                    $map['client_address'] = $index;
                    break;
                case 'CLIENTE_REFERENCIA':
                    $map['client_reference'] = $index;
                    break;
                case 'CLIENTE_DISTRITO':
                    $map['client_district'] = $index;
                    break;
                case 'CLIENTE_PROVINCIA':
                    $map['client_province'] = $index;
                    break;
                case 'CLIENTE_DEPARTAMENTO':
                    $map['client_department'] = $index;
                    break;
                case 'CLIENTE_CODIGO_POSTAL':
                    $map['client_postal_code'] = $index;
                    break;
                
                // Sección Lote
                case 'LOTE_NUMERO':
                    $map['lot_number'] = $index;
                    break;
                case 'LOTE_MANZANA':
                    $map['lot_manzana'] = $index;
                    break;
                case 'LOTE_AREA_TOTAL':
                    $map['lot_area_total'] = $index;
                    break;
                case 'LOTE_AREA_CONSTRUIDA':
                    $map['lot_area_built'] = $index;
                    break;
                case 'LOTE_PRECIO_TOTAL':
                    $map['lot_total_price'] = $index;
                    break;
                case 'LOTE_MONEDA':
                    $map['lot_currency'] = $index;
                    break;
                case 'LOTE_ESTADO':
                    $map['lot_status'] = $index;
                    break;
                case 'LOTE_OBSERVACIONES':
                    $map['lot_observations'] = $index;
                    break;
                
                // Sección Información Financiera
                case 'PRECIO_TOTAL':
                    $map['total_price'] = $index;
                    break;
                case 'CUOTA_INICIAL':
                    $map['down_payment'] = $index;
                    break;
                case 'MONTO_FINANCIADO':
                    $map['financing_amount'] = $index;
                    break;
                case 'TASA_INTERES':
                    $map['interest_rate'] = $index;
                    break;
                case 'NUMERO_CUOTAS':
                    $map['installments'] = $index;
                    break;
                case 'MONTO_CUOTA':
                    $map['monthly_payment'] = $index;
                    break;
                case 'PAGO_BALLOON':
                    $map['balloon_payment'] = $index;
                    break;
                case 'SEPARACION':
                    $map['separation'] = $index;
                    break;
                case 'DEPOSITO_REFERENCIA':
                    $map['deposit_reference'] = $index;
                    break;
                case 'FECHA_PAGO_DEPOSITO':
                    $map['deposit_paid_at'] = $index;
                    break;
                case 'PAGO_DIRECTO':
                    $map['direct_payment'] = $index;
                    break;
                case 'REEMBOLSO':
                    $map['refund'] = $index;
                    break;
                case 'TOTAL_INICIAL':
                    $map['total_initial'] = $index;
                    break;
                case 'PAGO_INICIAL':
                    $map['initial_payment'] = $index;
                    break;
                
                // Nuevos campos financieros migrados desde Lote
                case 'BPP':
                    $map['BPP'] = $index;
                    break;
                case 'BFH':
                    $map['BFH'] = $index;
                    break;
                case 'CUOTA_INICIAL_QUOTA':
                    $map['initial_quota'] = $index;
                    break;
                
                // Sección Contrato
                case 'CONTRATO_NUMERO':
                    $map['contract_number'] = $index;
                    break;
                case 'CONTRATO_TIPO':
                    $map['contract_type'] = $index;
                    break;
                case 'CONTRATO_FECHA_FIRMA':
                    $map['contract_signature_date'] = $index;
                    break;
                case 'CONTRATO_FECHA_INICIO':
                    $map['contract_start_date'] = $index;
                    break;
                case 'CONTRATO_FECHA_FIN':
                    $map['contract_end_date'] = $index;
                    break;
                case 'CONTRATO_ESTADO':
                    $map['contract_status'] = $index;
                    break;
                case 'CONTRATO_OBSERVACIONES':
                    $map['contract_observations'] = $index;
                    break;
                case 'ESTADO_CONTRATO':
                    $map['contract_general_status'] = $index;
                    break;
            }
        }
        
        return $map;
    }

    /**
     * Mapear datos de una fila
     */
    private function mapRowData(array $row, array $headerMap): array
    {
        $data = [];
        
        foreach ($headerMap as $field => $index) {
            $data[$field] = isset($row[$index]) ? trim($row[$index]) : null;
        }
        
        return $data;
    }

    /**
     * Verificar si una fila está vacía
     */
    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, function($value) {
            return !empty(trim($value));
        }));
    }

    /**
     * Procesar una fila de datos - Actualizado para template integral
     */
    private function processRow(array $data, int $rowNumber): void
    {
        // Validar datos requeridos
        $this->validateRowData($data, $rowNumber);
        
        // Buscar o crear cliente con información completa
        $client = $this->findOrCreateClientIntegral($data);
        
        // Crear dirección del cliente si se proporciona
        if (!empty($data['client_address'])) {
            $this->createClientAddress($client, $data);
        }
        
        // Buscar o crear lote con información completa
        $lot = $this->findOrCreateLotIntegral($data);
        
        // Buscar asesor
        $advisor = $this->findAdvisorIntegral($data);
        
        // Crear reservación
        $reservation = $this->createReservationIntegral($client, $lot, $data);
        
        // Crear contrato si corresponde
        $operationType = 'reservacion';
        if ($this->shouldCreateContractIntegral($data)) {
            $this->createContractIntegral($reservation, $advisor, $data);
            $operationType = 'contrato';
        }
        
        // Actualizar status del lote
        $this->updateLotStatus($lot, $operationType);
        
        $this->processed[] = [
            'row' => $rowNumber,
            'client' => $client->first_name . ' ' . $client->last_name,
            'lot' => $lot->num_lot,
            'advisor' => $advisor ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'No asignado',
            'operation_type' => $operationType
        ];
    }

    /**
     * Validar datos de una fila - Actualizado para template actual
     */
    private function validateRowData(array $data, int $rowNumber): void
    {
        if (empty($data['client_full_name'])) {
            throw new Exception("Fila {$rowNumber}: Nombre de cliente es requerido");
        }
        
        if (empty($data['lot_number'])) {
            throw new Exception("Fila {$rowNumber}: Número de lote es requerido");
        }
        
        if (empty($data['advisor_name'])) {
            throw new Exception("Fila {$rowNumber}: Asesor es requerido");
        }
    }

    /**
     * Buscar o crear cliente con información del template actual
     */
    private function findOrCreateClientIntegral(array $data): Client
    {
        $fullName = $data['client_full_name'] ?? '';
        $phone1 = $data['client_phone1'] ?? null;
        $phone2 = $data['client_phone2'] ?? null;
        $docNumber = $data['client_doc_number'] ?? '';
        
        // Separar nombre completo
        $nameParts = $this->splitFullName($fullName);
        $firstName = $nameParts['first_name'];
        $lastName = $nameParts['last_name'];
        
        // 1. Buscar cliente existente por número de documento (prioridad más alta)
        $client = null;
        if (!empty($docNumber)) {
            $client = Client::where('doc_number', $docNumber)->first();
        }
        
        // 2. Si no se encuentra por documento, buscar por teléfono
        if (!$client && $phone1) {
            $client = Client::where('primary_phone', $phone1)
                          ->orWhere('secondary_phone', $phone1)->first();
        }
        if (!$client && $phone2) {
            $client = Client::where('primary_phone', $phone2)
                          ->orWhere('secondary_phone', $phone2)->first();
        }
        
        if ($client) {
            // Actualizar información si es necesario
            $this->updateClientInfo($client, $data);
            return $client;
        }
        
        // 3. Solo crear nuevo cliente si no se encuentra ninguno existente
        return Client::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'doc_type' => $data['client_doc_type'] ?? 'DNI',
            'doc_number' => $docNumber,
            'primary_phone' => $phone1,
            'secondary_phone' => $phone2,
            'type' => 'client',
            'observations' => $data['client_observations'] ?? null
        ]);
    }

    /**
     * Actualizar información del cliente
     */
    private function updateClientInfo(Client $client, array $data): void
    {
        $updates = [];
        
        if (!empty($data['client_phone2']) && empty($client->secondary_phone)) {
            $updates['secondary_phone'] = $data['client_phone2'];
        }
        
        if (!empty($data['client_observations']) && empty($client->observations)) {
            $updates['observations'] = $data['client_observations'];
        }
        
        if (!empty($updates)) {
            $client->update($updates);
        }
    }

    /**
     * Crear dirección del cliente
     */
    private function createClientAddress(Client $client, array $data): void
    {
        // Verificar si ya existe una dirección similar
        $existingAddress = Address::where('client_id', $client->client_id)
            ->where('line1', $data['client_address'])
            ->first();
            
        if (!$existingAddress) {
            Address::create([
                'client_id' => $client->client_id,
                'line1' => $data['client_address'],
                'line2' => $data['client_reference'] ?? null,
                'city' => $data['client_district'] ?? null,
                'state' => $data['client_province'] ?? null,
                'country' => $data['client_department'] ?? 'Perú',
                'zip_code' => $data['client_postal_code'] ?? null
            ]);
        }
    }

    /**
     * Buscar o crear lote con información del template actual
     */
    private function findOrCreateLotIntegral(array $data): Lot
    {
        $lotNumber = $data['lot_number'];
        $manzanaName = $data['lot_manzana'] ?? null;
        
        // Buscar lote existente
        $query = Lot::with('financialTemplate')->where('num_lot', $lotNumber);
        
        if ($manzanaName) {
            $query->whereHas('manzana', function($q) use ($manzanaName) {
                $q->where('name', 'LIKE', "%{$manzanaName}%");
            });
        }
        
        $lot = $query->first();
        
        if (!$lot) {
            throw new Exception("Lote {$lotNumber}" . ($manzanaName ? " en manzana {$manzanaName}" : '') . " no encontrado. El lote debe existir antes de importar contratos.");
        }
        
        // Verificar que el lote tenga template financiero
        if (!$lot->financialTemplate) {
            throw new Exception("El lote {$lotNumber}" . ($manzanaName ? " en manzana {$manzanaName}" : '') . " no tiene template financiero configurado.");
        }
        
        return $lot;
    }

    /**
     * Actualizar status del lote según el tipo de operación
     */
    private function updateLotStatus(Lot $lot, string $operationType): void
    {
        $newStatus = $operationType === 'contrato' ? 'vendido' : 'reservado';
        
        if ($lot->status !== $newStatus) {
            $lot->update(['status' => $newStatus]);
            Log::info("Status del lote {$lot->num_lot} actualizado a: {$newStatus}");
        }
    }

    /**
     * Buscar asesor con información del template actual - VERSIÓN MEJORADA
     * Incluye logging detallado y mejor lógica de coincidencia
     * NUNCA retorna null - siempre encuentra un asesor válido
     */
    private function findAdvisorIntegral(array $data): Employee
    {
        $advisorName = $data["advisor_name"] ?? "";
        $advisorCode = $data["advisor_code"] ?? "";
        
        Log::info("[IMPORT] Buscando asesor", [
            "advisor_name" => $advisorName,
            "advisor_code" => $advisorCode,
            "row_data" => $data
        ]);
        
        // 1. Buscar por código primero si existe
        if (!empty($advisorCode)) {
            $advisor = Employee::where("employee_code", $advisorCode)
                              ->where("employee_type", "asesor_inmobiliario")
                              ->first();
            if ($advisor) {
                Log::info("[IMPORT] Asesor encontrado por código", [
                    "advisor_id" => $advisor->employee_id,
                    "advisor_code" => $advisor->employee_code,
                    "advisor_name" => $advisor->user->first_name . " " . $advisor->user->last_name
                ]);
                return $this->preventAdvisorConcentration($advisor);
            }
            Log::warning("[IMPORT] No se encontró asesor con código: {$advisorCode}");
        }
        
        // 2. Si no se encuentra por código, buscar por nombre con lógica mejorada
        if (!empty($advisorName)) {
            $normalizedSearchName = $this->normalizeText($advisorName);
            
            Log::info("[IMPORT] Búsqueda por nombre", [
                "original_name" => $advisorName,
                "normalized_name" => $normalizedSearchName
            ]);
            
            // Obtener todos los asesores activos
            $advisors = Employee::with("user")
                              ->where("employee_type", "asesor_inmobiliario")
                              ->where("employment_status", "activo")
                              ->get();
            
            $matches = [];
            
            foreach ($advisors as $advisor) {
                if (!$advisor->user) continue;
                
                $score = $this->calculateNameMatchScore($normalizedSearchName, $advisor);
                
                if ($score > 0) {
                    $matches[] = [
                        "advisor" => $advisor,
                        "score" => $score,
                        "reason" => $this->getMatchReason($normalizedSearchName, $advisor)
                    ];
                }
            }
            
            // Ordenar por score descendente
            usort($matches, function($a, $b) {
                return $b["score"] - $a["score"];
            });
            
            if (!empty($matches)) {
                $bestMatch = $matches[0];
                
                Log::info("[IMPORT] Asesor encontrado por nombre", [
                    "advisor_id" => $bestMatch["advisor"]->employee_id,
                    "advisor_name" => $bestMatch["advisor"]->user->first_name . " " . $bestMatch["advisor"]->user->last_name,
                    "match_score" => $bestMatch["score"],
                    "match_reason" => $bestMatch["reason"],
                    "total_candidates" => count($matches)
                ]);
                
                return $this->preventAdvisorConcentration($bestMatch["advisor"]);
            }
            
            Log::warning("[IMPORT] No se encontró coincidencia para nombre: {$normalizedSearchName}");
        }
        
        // 3. Fallback: usar sistema de rotación equitativa
        Log::warning("[IMPORT] Usando sistema de rotación", [
            "reason" => "No se encontró coincidencia por código ni nombre",
            "advisor_name" => $advisorName,
            "advisor_code" => $advisorCode
        ]);
        
        return $this->getNextAdvisorInRotation();
    }

    /**
     * Verificar y prevenir concentración excesiva de contratos en un asesor
     */
    private function preventAdvisorConcentration(Employee $advisor): Employee
    {
        // Obtener total de contratos del último mes
        $oneMonthAgo = Carbon::now()->subMonth();
        $totalContracts = Contract::where('sign_date', '>=', $oneMonthAgo)->count();
        
        if ($totalContracts < 10) {
            // Si hay pocos contratos, no aplicar restricción
            return $advisor;
        }
        
        // Obtener contratos del asesor en el último mes
        $advisorContracts = Contract::where('advisor_id', $advisor->employee_id)
                                  ->where('sign_date', '>=', $oneMonthAgo)
                                  ->count();
        
        // Calcular porcentaje de concentración
        $concentrationPercentage = ($advisorContracts / $totalContracts) * 100;
        
        // Si el asesor tiene más del 40% de los contratos, usar rotación
        if ($concentrationPercentage > 40) {
            Log::warning('[IMPORT] Concentración excesiva detectada', [
                'advisor_id' => $advisor->employee_id,
                'advisor_name' => $advisor->user->first_name . ' ' . $advisor->user->last_name,
                'concentration_percentage' => $concentrationPercentage,
                'advisor_contracts' => $advisorContracts,
                'total_contracts' => $totalContracts
            ]);
            
            return $this->getNextAdvisorInRotation();
        }
        
        return $advisor;
    }

    /**
     * Obtener el siguiente asesor en rotación para distribución equitativa
     */
    private function getNextAdvisorInRotation(): Employee
    {
        // Obtener todos los asesores activos
        $advisors = Employee::where('employee_type', 'asesor_inmobiliario')
                           ->where('employment_status', 'activo')
                           ->with('user')
                           ->get();
        
        if ($advisors->isEmpty()) {
            Log::error('[IMPORT] No hay asesores inmobiliarios activos disponibles');
            throw new \Exception('No hay asesores inmobiliarios activos disponibles para asignar contratos.');
        }
        
        // Obtener estadísticas de contratos por asesor en el último mes
        $oneMonthAgo = Carbon::now()->subMonth();
        $advisorStats = [];
        
        foreach ($advisors as $advisor) {
            $contractCount = Contract::where('advisor_id', $advisor->employee_id)
                                   ->where('sign_date', '>=', $oneMonthAgo)
                                   ->count();
            
            $advisorStats[] = [
                'advisor' => $advisor,
                'contract_count' => $contractCount,
                'last_assigned' => Contract::where('advisor_id', $advisor->employee_id)
                                          ->latest('sign_date')
                                          ->value('sign_date') ?? '1970-01-01'
            ];
        }
        
        // Ordenar por: 1) menor número de contratos, 2) asignación más antigua
        usort($advisorStats, function($a, $b) {
            if ($a['contract_count'] === $b['contract_count']) {
                return strcmp($a['last_assigned'], $b['last_assigned']);
            }
            return $a['contract_count'] - $b['contract_count'];
        });
        
        $selectedAdvisor = $advisorStats[0]['advisor'];
        
        Log::info('[IMPORT] Asesor seleccionado por rotación', [
            'advisor_id' => $selectedAdvisor->employee_id,
            'advisor_name' => $selectedAdvisor->user->first_name . ' ' . $selectedAdvisor->user->last_name,
            'current_contracts' => $advisorStats[0]['contract_count'],
            'last_assigned' => $advisorStats[0]['last_assigned'],
            'total_advisors' => count($advisorStats)
        ]);
        
        return $selectedAdvisor;
    }

    /**
     * Calcular score de coincidencia entre nombre buscado y asesor
     */
    private function calculateNameMatchScore(string $searchName, Employee $advisor): int
    {
        if (!$advisor->user) return 0;
        
        $firstName = $this->normalizeText($advisor->user->first_name ?? "");
        $lastName = $this->normalizeText($advisor->user->last_name ?? "");
        $fullName = $this->normalizeText(($advisor->user->first_name ?? "") . " " . ($advisor->user->last_name ?? ""));
        $fullNameReverse = $this->normalizeText(($advisor->user->last_name ?? "") . " " . ($advisor->user->first_name ?? ""));
        
        $score = 0;
        
        // Coincidencia exacta del nombre completo (score más alto)
        if ($searchName === $fullName || $searchName === $fullNameReverse) {
            $score += 100;
        }
        
        // Coincidencia exacta de nombre o apellido
        if ($searchName === $firstName || $searchName === $lastName) {
            $score += 80;
        }
        
        // Contiene el nombre completo
        if (strpos($fullName, $searchName) !== false || strpos($fullNameReverse, $searchName) !== false) {
            $score += 60;
        }
        
        // Contiene nombre o apellido
        if (strpos($firstName, $searchName) !== false || strpos($lastName, $searchName) !== false) {
            $score += 40;
        }
        
        // El nombre buscado contiene nombre o apellido del asesor
        if (strpos($searchName, $firstName) !== false || strpos($searchName, $lastName) !== false) {
            $score += 30;
        }
        
        // Coincidencia de palabras individuales
        $searchWords = explode(" ", $searchName);
        $advisorWords = array_merge(explode(" ", $firstName), explode(" ", $lastName));
        
        $wordMatches = 0;
        foreach ($searchWords as $searchWord) {
            if (strlen($searchWord) > 2) {
                foreach ($advisorWords as $advisorWord) {
                    if (strlen($advisorWord) > 2 && $searchWord === $advisorWord) {
                        $wordMatches++;
                        break;
                    }
                }
            }
        }
        
        $score += $wordMatches * 20;
        
        return $score;
    }

    /**
     * Obtener razón de la coincidencia para logging
     */
    private function getMatchReason(string $searchName, Employee $advisor): string
    {
        if (!$advisor->user) return "Sin usuario";
        
        $firstName = $this->normalizeText($advisor->user->first_name ?? "");
        $lastName = $this->normalizeText($advisor->user->last_name ?? "");
        $fullName = $this->normalizeText(($advisor->user->first_name ?? "") . " " . ($advisor->user->last_name ?? ""));
        $fullNameReverse = $this->normalizeText(($advisor->user->last_name ?? "") . " " . ($advisor->user->first_name ?? ""));
        
        if ($searchName === $fullName) return "Coincidencia exacta nombre completo";
        if ($searchName === $fullNameReverse) return "Coincidencia exacta nombre completo invertido";
        if ($searchName === $firstName) return "Coincidencia exacta nombre";
        if ($searchName === $lastName) return "Coincidencia exacta apellido";
        if (strpos($fullName, $searchName) !== false) return "Contiene en nombre completo";
        if (strpos($fullNameReverse, $searchName) !== false) return "Contiene en nombre completo invertido";
        if (strpos($firstName, $searchName) !== false) return "Contiene en nombre";
        if (strpos($lastName, $searchName) !== false) return "Contiene en apellido";
        if (strpos($searchName, $firstName) !== false) return "Nombre contenido en búsqueda";
        if (strpos($searchName, $lastName) !== false) return "Apellido contenido en búsqueda";
        
        return "Coincidencia de palabras";
    }

    /**
     * Crear reservación con información del template actual
     */
    private function createReservationIntegral(Client $client, Lot $lot, array $data): Reservation
    {
        $reservationDate = $this->parseDate($data['sale_date'] ?? now());
        $depositAmount = $this->parseDecimal($data['separation'] ?? 100);
        $depositReference = $data['deposit_reference'] ?? null;
        $depositPaidAt = !empty($data['deposit_paid_at']) ? $this->parseDate($data['deposit_paid_at']) : null;
        
        // Buscar el asesor (siempre retorna un Employee válido)
        $advisor = $this->findAdvisorIntegral($data);
        
        return Reservation::create([
            'lot_id' => $lot->lot_id,
            'client_id' => $client->client_id,
            'advisor_id' => $advisor->employee_id,
            'reservation_date' => $reservationDate,
            'expiration_date' => Carbon::parse($reservationDate)->addDays(30),
            'deposit_amount' => $depositAmount,
            'deposit_method' => 'efectivo',
            'deposit_reference' => $depositReference,
            'deposit_paid_at' => $depositPaidAt,
            'status' => 'completada'
        ]);
    }

    /**
     * Determinar si se debe crear contrato - Actualizado para template actual
     */
    private function shouldCreateContractIntegral(array $data): bool
    {
        $hasContract = strtolower($data['has_contract'] ?? '') === 'si';
        $financingAmount = $this->parseDecimal($data['financing_amount'] ?? 0);
        $downPayment = $this->parseDecimal($data['down_payment'] ?? 0);
        $contractStatus = $data['contract_status'] ?? '';
        
        return $hasContract || $financingAmount > 0 || $downPayment > 0 || !empty($contractStatus);
    }

    /**
     * Crear contrato con información del template financiero del lote
     */
    private function createContractIntegral(Reservation $reservation, Employee $advisor, array $data): Contract
    {
        // Obtener el lote y su template financiero
        $lot = $reservation->lot;
        $template = $lot->financialTemplate;
        
        if (!$template) {
            throw new Exception("El lote {$lot->num_lot} no tiene template financiero configurado.");
        }
        
        // Determinar tipo de financiamiento basado en los datos del Excel
        $installments = (int)($data['installments'] ?? $data['installments_alt'] ?? 0);
        $isFinanced = $installments > 0;
        
        // Obtener precios del template financiero
        if ($isFinanced) {
            // Financiamiento: usar precio_venta
            $totalPrice = $template->precio_venta;
            $downPayment = $template->cuota_inicial;
            $balloonPayment = $template->cuota_balon;
            $financingAmount = $template->getFinancingAmount();
            
            // Obtener cuota mensual del template según el número de cuotas
            $monthlyPayment = $template->getInstallmentAmount($installments);
            
            if ($monthlyPayment <= 0) {
                throw new Exception("El lote {$lot->num_lot} no tiene configurado financiamiento para {$installments} cuotas.");
            }
        } else {
            // Pago de contado: usar precio_contado si está disponible
            if ($template->hasCashPrice()) {
                $totalPrice = $template->precio_contado;
                $downPayment = $template->precio_contado; // Todo el monto como cuota inicial
                $financingAmount = 0;
                $monthlyPayment = 0;
                $balloonPayment = 0;
                $installments = 0;
            } else {
                // Si no hay precio de contado, usar precio de venta
                $totalPrice = $template->precio_venta;
                $downPayment = $template->precio_venta;
                $financingAmount = 0;
                $monthlyPayment = 0;
                $balloonPayment = 0;
                $installments = 0;
            }
        }
        
        $signDate = $this->parseDate($data['sale_date'] ?? now());
        $status = $data['contract_status'] ?? 'vigente';
        
        // Generar número de contrato
        $contractNumber = $this->generateContractNumber();
        
        Log::info("Creando contrato con precios del template financiero", [
            'lot_number' => $lot->num_lot,
            'total_price' => $totalPrice,
            'down_payment' => $downPayment,
            'financing_amount' => $financingAmount,
            'monthly_payment' => $monthlyPayment,
            'installments' => $installments,
            'is_financed' => $isFinanced
        ]);
        
        return Contract::create([
            'reservation_id' => $reservation->reservation_id,
            'advisor_id' => $advisor->employee_id,
            'contract_number' => $contractNumber,
            'sign_date' => $signDate,
            'total_price' => $totalPrice,
            'down_payment' => $downPayment,
            'financing_amount' => $financingAmount,
            'interest_rate' => 0,
            'term_months' => $installments,
            'monthly_payment' => $monthlyPayment,
            'balloon_payment' => $balloonPayment,
            'currency' => 'S/',
            'status' => $status,
            
            // Campos financieros del template:
            'funding' => 0, // No usado en el template actual
            'bpp' => $template->bono_bpp ?? 0,
            'bfh' => 0, // No usado en el template actual
            'initial_quota' => $template->cuota_inicial
        ]);
    }

    /**
     * Separar nombre completo en nombre y apellido
     */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName));
        
        if (count($parts) === 1) {
            return [
                'first_name' => $parts[0],
                'last_name' => ''
            ];
        }
        
        if (count($parts) === 2) {
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[1]
            ];
        }
        
        // Para 3 o más palabras: los dos últimos son apellidos
        $lastName = implode(' ', array_slice($parts, -2));
        $firstName = implode(' ', array_slice($parts, 0, -2));
        
        return [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
    }

    /**
     * Parsear fecha
     */
    private function parseDate($date): string
    {
        if (empty($date)) {
            return now()->format('Y-m-d');
        }
        
        Log::info("Parseando fecha: {$date} (tipo: " . gettype($date) . ")");
        
        try {
            // Si es un número (Excel date serial)
            if (is_numeric($date) && $date > 0) {
                // Excel cuenta desde 1900-01-01, pero tiene un bug con 1900 como año bisiesto
                if ($date > 59) {
                    $parsed = Carbon::createFromDate(1900, 1, 1)->addDays($date - 2);
                } else {
                    $parsed = Carbon::createFromDate(1900, 1, 1)->addDays($date - 1);
                }
                Log::info("Fecha parseada desde serial: " . $parsed->format('Y-m-d'));
                return $parsed->format('Y-m-d');
            }
            
            // Convertir a string si no lo es
            $dateString = (string) $date;
            
            // Intentar varios formatos de fecha
            $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];
            
            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $dateString);
                    if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                        Log::info("Fecha parseada con formato {$format}: " . $parsed->format('Y-m-d'));
                        return $parsed->format('Y-m-d');
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            // Intentar parsear con Carbon directamente
            $parsed = Carbon::parse($dateString);
            if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                Log::info("Fecha parseada con Carbon::parse: " . $parsed->format('Y-m-d'));
                return $parsed->format('Y-m-d');
            }
            
        } catch (Exception $e) {
            Log::warning("No se pudo parsear la fecha: {$date} - Error: " . $e->getMessage());
        }
        
        Log::warning("Usando fecha actual como fallback para: {$date}");
        return now()->format('Y-m-d');
    }

    /**
     * Parsear decimal
     */
    private function parseDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        // Convertir a string si no lo es
        $stringValue = (string) $value;
        
        // Remover símbolos de moneda y espacios
        $cleaned = preg_replace('/[S\/\$\s]/', '', $stringValue);
        
        // Remover otros caracteres no numéricos excepto punto y coma
        $cleaned = preg_replace('/[^0-9.,]/', '', $cleaned);
        
        // Manejar formato peruano: 1,130.00
        if (preg_match('/^\d{1,3}(,\d{3})*\.\d{2}$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        } else {
            $cleaned = str_replace(',', '.', $cleaned);
        }
        
        return (float) $cleaned;
    }

    /**
     * Generar número de contrato único
     */
    private function generateContractNumber(): string
    {
        do {
            $number = 'CON' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Contract::where('contract_number', $number)->exists());
        
        return $number;
    }
}