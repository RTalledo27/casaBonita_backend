<?php

namespace Modules\Sales\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\Address;
use Modules\HumanResources\Models\Employee;
use Modules\Inventory\Models\Lot;
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
        if ($this->shouldCreateContractIntegral($data)) {
            $this->createContractIntegral($reservation, $advisor, $data);
        }
        
        $this->processed[] = [
            'row' => $rowNumber,
            'client' => $client->first_name . ' ' . $client->last_name,
            'lot' => $lot->num_lot,
            'advisor' => $advisor ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'No asignado'
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
        $query = Lot::where('num_lot', $lotNumber);
        
        if ($manzanaName) {
            $query->whereHas('manzana', function($q) use ($manzanaName) {
                $q->where('name', 'LIKE', "%{$manzanaName}%");
            });
        }
        
        $lot = $query->first();
        
        if ($lot) {
            // Actualizar información del lote si es necesario
            $this->updateLotInfo($lot, $data);
            return $lot;
        }
        
        // Buscar o crear manzana
        $manzana = null;
        if ($manzanaName) {
            $manzana = Manzana::where('name', 'LIKE', "%{$manzanaName}%")->first();
            if (!$manzana) {
                $manzana = Manzana::create([
                    'name' => $manzanaName,
                    'project_id' => 1 // Asumir proyecto por defecto
                ]);
            }
        }
        
        // Buscar o crear tipo de calle por defecto
        $streetType = StreetType::firstOrCreate(
            ['name' => 'Calle'],
            ['name' => 'Calle']
        );
        
        // Crear nuevo lote SIN campos financieros
        return Lot::create([
            'num_lot' => $lotNumber,
            'manzana_id' => $manzana ? $manzana->manzana_id : null,
            'street_type_id' => $streetType->street_type_id,
            'area_m2' => $this->parseDecimal($data['area_m2'] ?? 0),
            'area_construction_m2' => $this->parseDecimal($data['area_construction_m2'] ?? 0),
            'total_price' => $this->parseDecimal($data['total_price'] ?? 0),  // Precio base
            'currency' => 'S/',
            'status' => 'disponible'
            // Campos financieros removidos: funding, BPP, BFH, initial_quota
        ]);
    }

    /**
     * Actualizar información del lote
     */
    private function updateLotInfo(Lot $lot, array $data): void
    {
        $updates = [];
        
        if (!empty($data['total_price']) && empty($lot->total_price)) {
            $updates['total_price'] = $this->parseDecimal($data['total_price']);
        }
        
        if (!empty($updates)) {
            $lot->update($updates);
        }
    }

    /**
     * Buscar asesor con información del template actual
     * NUNCA retorna null - siempre encuentra un asesor válido
     */
    private function findAdvisorIntegral(array $data): Employee
    {
        $advisorName = $data['advisor_name'] ?? '';
        $advisorCode = $data['advisor_code'] ?? '';
        
        Log::info("Buscando asesor - Nombre: '{$advisorName}', Código: '{$advisorCode}'");
        
        // Buscar por código primero si existe
        if (!empty($advisorCode)) {
            $advisor = Employee::where('employee_code', $advisorCode)
                              ->where('employee_type', 'asesor_inmobiliario')
                              ->first();
            if ($advisor) {
                Log::info("Asesor encontrado por código: {$advisor->employee_code} - {$advisor->user->first_name} {$advisor->user->last_name}");
                return $advisor;
            }
            Log::warning("No se encontró asesor con código: {$advisorCode}");
        }
        
        // Si no se encuentra por código o no hay código, buscar por nombre
        if (!empty($advisorName)) {
            // Búsqueda flexible por nombre (case insensitive)
            $advisor = Employee::whereHas('user', function($query) use ($advisorName) {
                    $advisorNameLower = strtolower($advisorName);
                    $query->whereRaw('LOWER(first_name) LIKE ?', ["%{$advisorNameLower}%"])
                          ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$advisorNameLower}%"])
                          ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ["%{$advisorNameLower}%"])
                          ->orWhereRaw("LOWER(CONCAT(last_name, ' ', first_name)) LIKE ?", ["%{$advisorNameLower}%"]);
                })
                ->where('employee_type', 'asesor_inmobiliario')
                ->first();
                
            if ($advisor) {
                Log::info("Asesor encontrado por nombre: {$advisor->user->first_name} {$advisor->user->last_name}");
                return $advisor;
            }
            Log::warning("No se encontró asesor con nombre: {$advisorName}");
        }
        
        // Fallback 1: Buscar asesores por defecto
        Log::info("Buscando asesor por defecto...");
        $defaultAdvisor = Employee::where('employee_type', 'asesor_inmobiliario')
                                 ->whereIn('employee_code', ['DEFAULT', 'ADMIN', 'SISTEMA'])
                                 ->first();
        
        if ($defaultAdvisor) {
            Log::info("Asesor por defecto encontrado: {$defaultAdvisor->employee_code} - {$defaultAdvisor->user->first_name} {$defaultAdvisor->user->last_name}");
            return $defaultAdvisor;
        }
        
        // Fallback 2: Obtener el primer asesor disponible
        Log::info("Buscando primer asesor disponible...");
        $firstAdvisor = Employee::where('employee_type', 'asesor_inmobiliario')
                               ->with('user')
                               ->first();
        
        if ($firstAdvisor) {
            Log::info("Primer asesor disponible: {$firstAdvisor->user->first_name} {$firstAdvisor->user->last_name}");
            return $firstAdvisor;
        }
        
        // Si no hay ningún asesor en el sistema, lanzar excepción
        Log::error("No se encontró ningún asesor inmobiliario en el sistema");
        throw new \Exception('No se encontró ningún asesor inmobiliario en el sistema. Por favor, cree al menos un asesor antes de importar contratos.');
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
     * Crear contrato con información del template actual
     */
    private function createContractIntegral(Reservation $reservation, Employee $advisor, array $data): Contract
    {
        $totalPrice = $this->parseDecimal($data['total_price'] ?? 0);
        $downPayment = $this->parseDecimal($data['down_payment'] ?? 0);
        $financingAmount = $this->parseDecimal($data['financing_amount'] ?? 0);
        $balloonPayment = $this->parseDecimal($data['balloon_payment'] ?? 0);
        $installments = (int)($data['installments'] ?? $data['installments_alt'] ?? 0);
        $monthlyPayment = $this->parseDecimal($data['monthly_payment'] ?? 0);

        $initialPayment = $this->parseDecimal($data['initial_payment'] ?? 0);
        $directPayment = $this->parseDecimal($data['direct_payment'] ?? 0);
        
        $signDate = $this->parseDate($data['sale_date'] ?? now());
        $status = $data['contract_status'] ?? 'vigente';
        
        // Generar número de contrato
        $contractNumber = $this->generateContractNumber();
        
        // Calcular cuota mensual si no se proporciona
        if ($monthlyPayment == 0 && $installments > 0 && $financingAmount > 0) {
            $monthlyPayment = $financingAmount / $installments;
        }
        

        
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
            
            // Nuevos campos financieros (migrados desde lote):
            'funding' => $this->parseDecimal($data['funding'] ?? 0),
            'bpp' => $this->parseDecimal($data['BPP'] ?? 0),
            'bfh' => $this->parseDecimal($data['BFH'] ?? 0),
            'initial_quota' => $this->parseDecimal($data['initial_quota'] ?? 0)
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