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
use Modules\Security\Models\User;

class ContractImportService
{
    private array $errors = [];
    private array $processed = [];
    private int $successCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;

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
     * Procesar archivo Excel de contratos/reservaciones - VERSIÓN SIMPLIFICADA
     */
    public function processExcelSimplified(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                throw new Exception('El archivo está vacío');
            }

            $headers = array_shift($rows);
            Log::info('Headers encontrados:', $headers);
            
            $validation = $this->validateExcelStructureSimplified($headers);
            
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos desde la fila 2 (después del header)
                
                try {
                    Log::info("Procesando fila {$rowNumber}", ['row_data' => $row, 'headers' => $headers]);
                    $result = $this->processRowSimplified($row, $headers);
                    
                    if ($result['status'] === 'success') {
                        $this->successCount++;
                        $this->processed[] = [
                            'row' => $rowNumber,
                            'client_id' => $result['client_id'],
                            'lot_id' => $result['lot_id'],
                            'reservation_id' => $result['reservation_id'],
                            'contract_id' => $result['contract_id'] ?? null,
                            'message' => $result['message']
                        ];
                    } elseif ($result['status'] === 'skipped') {
                        $this->skippedCount++;
                    } else {
                        $this->errorCount++;
                        $this->errors[] = [
                            'row' => $rowNumber,
                            'error' => $result['message'],
                            'data' => $row
                        ];
                    }
                    
                } catch (Exception $e) {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                    Log::error("Error procesando fila {$rowNumber}: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                }
            }

            DB::commit();

            return [
                'success' => true,
                'processed' => $this->successCount,
                'errors' => $this->errorCount,
                'skipped' => $this->skippedCount,
                'error_details' => $this->errors,
                'message' => "Procesadas {$this->successCount} filas exitosamente, {$this->errorCount} errores, {$this->skippedCount} filas omitidas por datos incompletos"
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en importación de contratos (simplificada): ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processed' => $this->successCount,
                'errors' => $this->errorCount,
                'skipped' => $this->skippedCount,
                'error_details' => $this->errors
            ];
        }
    }

    /**
     * Procesar archivo Excel de contratos/reservaciones - VERSIÓN ANTIGUA
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
                    
                    // Validar campos requeridos de lote antes de procesar
                    if (!$this->validateRequiredLotFields($data, $rowNumber)) {
                        $this->skippedCount++;
                        continue;
                    }
                    
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
                'skipped' => $this->skippedCount,
                'error_details' => $this->errors,
                'message' => "Procesadas {$this->successCount} filas exitosamente, {$this->errorCount} errores, {$this->skippedCount} filas omitidas por datos incompletos"
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en importación de contratos: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processed' => $this->successCount,
                'errors' => $this->errorCount,
                'skipped' => $this->skippedCount,
                'error_details' => $this->errors
            ];
        }
    }

    /**
     * Validar estructura del archivo Excel - VERSIÓN SIMPLIFICADA (14 campos)
     * Acepta múltiples variaciones de nombres de columnas para mayor flexibilidad
     */
    public function validateExcelStructureSimplified(array $headers): array
    {
        // Definir campos requeridos con sus variaciones aceptadas
        $requiredFieldsWithVariations = [
            'asesor_nombre' => ['ASESOR_NOMBRE'],
            'asesor_codigo' => ['ASESOR_CODIGO'], 
            'asesor_email' => ['ASESOR_EMAIL'],
            'cliente_nombre_completo' => ['CLIENTE_NOMBRE_COMPLETO', 'CLIENTE_NOMBRES'],
            'cliente_tipo_doc' => ['CLIENTE_TIPO_DOC', 'CLIENTE_TIPO_DOCUMENTO', 'CLIENTE_DOCUMENTO'],
            'cliente_num_doc' => ['CLIENTE_NUM_DOC', 'CLIENTE_NUMERO_DOCUMENTO'],
            'cliente_telefono_1' => ['CLIENTE_TELEFONO_1', 'CLIENTE_TELEFONO'],
            'cliente_email' => ['CLIENTE_EMAIL'],
            'lote_numero' => ['LOTE_NUMERO'],
            'lote_manzana' => ['LOTE_MANZANA'],
            'fecha_venta' => ['FECHA_VENTA'],
            'tipo_operacion' => ['TIPO_OPERACION'],
            'observaciones' => ['OBSERVACIONES'],
            'estado_contrato' => ['ESTADO_CONTRATO', 'CONTRATO_ESTADO']
        ];

        // Convertir headers a mayúsculas para comparación
        $upperHeaders = array_map(function($header) {
            return trim(strtoupper($header));
        }, $headers);

        $missingFields = [];
        
        // Verificar cada campo requerido
        foreach ($requiredFieldsWithVariations as $fieldKey => $variations) {
            $found = false;
            
            // Buscar cualquiera de las variaciones del campo
            foreach ($variations as $variation) {
                if (in_array($variation, $upperHeaders)) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Usar la primera variación como nombre de referencia
                $missingFields[] = $variations[0];
            }
        }

        if (!empty($missingFields)) {
            return [
                'valid' => false,
                'error' => 'Faltan las siguientes columnas requeridas: ' . implode(', ', $missingFields)
            ];
        }

        return ['valid' => true];
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
        
        // COMPATIBILIDAD: Si no existe 'client_full_name' pero sí 'client_first_name' (CLIENTE_NOMBRES),
        // usar CLIENTE_NOMBRES como nombre completo para mantener compatibilidad
        if (!isset($map['client_full_name']) && isset($map['client_first_name'])) {
            $map['client_full_name'] = $map['client_first_name'];
            Log::info("Usando CLIENTE_NOMBRES como nombre completo para compatibilidad", [
                'column_index' => $map['client_first_name']
            ]);
        }
        
        return $map;
    }

    /**
     * Mapear headers del Excel simplificado a índices - Solo 15 campos esenciales
     */
    private function mapSimplifiedHeaders(array $headers): array
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = trim(strtoupper($header));
            
            // Mapeo de headers del template simplificado (15 campos)
            switch ($header) {
                // Sección Asesor (3 campos)
                case 'ASESOR_NOMBRE':
                    $map['asesor_nombre'] = $index;
                    break;
                case 'ASESOR_CODIGO':
                    $map['asesor_codigo'] = $index;
                    break;
                case 'ASESOR_EMAIL':
                    $map['asesor_email'] = $index;
                    break;
                
                // Sección Cliente (5 campos)
                case 'CLIENTE_NOMBRE_COMPLETO':
                case 'CLIENTE_NOMBRES': // Compatibilidad con variación de nombre
                    $map['client_full_name'] = $index;
                    break;
                case 'CLIENTE_DOCUMENTO': // Variación estándar
                case 'CLIENTE_TIPO_DOC': // Variación del template
                case 'CLIENTE_TIPO_DOCUMENTO': // Variación del Excel descargado
                    $map['cliente_tipo_doc'] = $index;
                    break;
                case 'CLIENTE_NUM_DOC':
                case 'CLIENTE_NUMERO_DOCUMENTO': // Variación del Excel descargado
                    $map['cliente_num_doc'] = $index;
                    break;
                case 'CLIENTE_TELEFONO': // Variación estándar
                case 'CLIENTE_TELEFONO_1': // Variación del template
                    $map['cliente_telefono_1'] = $index;
                    break;
                case 'CLIENTE_EMAIL':
                    $map['cliente_email'] = $index;
                    break;
                
                // Sección Lote (2 campos)
                case 'LOTE_NUMERO':
                    $map['lot_number'] = $index;
                    break;
                case 'LOTE_MANZANA':
                    $map['lot_manzana'] = $index;
                    break;
                
                // Sección Venta (4 campos)
                case 'FECHA_VENTA':
                    $map['fecha_venta'] = $index;
                    break;
                case 'TIPO_OPERACION':
                    $map['operation_type'] = $index;
                    break;
                case 'OBSERVACIONES':
                    $map['observaciones'] = $index;
                    break;
                case 'ESTADO_CONTRATO': // Variación del template
                case 'CONTRATO_ESTADO': // Variación estándar
                    $map['contract_status'] = $index;
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
     * Validar que los campos requeridos de lote estén completos
     * Omite filas que no tengan datos completos de lote y manzana
     */
    private function validateRequiredLotFields(array $data, int $rowNumber): bool
    {
        $lotNumber = $data['lot_number'] ?? '';
        $lotManzana = $data['lot_manzana'] ?? '';
        
        // Verificar que lot_number no esté vacío, nulo o contenga solo espacios
        $isLotNumberEmpty = empty(trim($lotNumber));
        
        // Verificar que lot_manzana no esté vacío, nulo o contenga solo espacios
        $isLotManzanaEmpty = empty(trim($lotManzana));
        
        // Si cualquiera de los campos está vacío, omitir la fila
        if ($isLotNumberEmpty || $isLotManzanaEmpty) {
            $missingFields = [];
            if ($isLotNumberEmpty) $missingFields[] = 'LOTE_NUMERO';
            if ($isLotManzanaEmpty) $missingFields[] = 'LOTE_MANZANA';
            
            Log::info("Fila {$rowNumber} omitida por datos incompletos de lote", [
                'missing_fields' => $missingFields,
                'lot_number' => $lotNumber,
                'lot_manzana' => $lotManzana,
                'reason' => 'Contrato no representa una venta concreta - faltan datos de lote'
            ]);
            
            return false;
        }
        
        return true;
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
        
        // Buscar lote con información completa
        $lot = $this->findOrCreateLotIntegral($data);
        
        // NUEVA LÓGICA: Verificar disponibilidad del lote antes de procesarlo
        $availability = $this->checkLotAvailability($lot);
        
        Log::info("Procesando fila {$rowNumber} - Verificación de lote", [
            'lot_number' => $lot->num_lot,
            'client_name' => $data['client_full_name'],
            'can_reassign' => $availability['can_reassign'],
            'reason' => $availability['reason'],
            'current_status' => $availability['current_status']
        ]);
        
        // Si el lote NO puede ser reasignado, saltar el procesamiento
        if (!$availability['can_reassign']) {
            Log::warning("Saltando procesamiento de fila {$rowNumber}", [
                'lot_number' => $lot->num_lot,
                'client_name' => $data['client_full_name'],
                'reason' => $availability['reason']
            ]);
            
            $this->processed[] = [
                'row' => $rowNumber,
                'client' => $data['client_full_name'],
                'lot' => $lot->num_lot,
                'advisor' => 'N/A',
                'operation_type' => 'saltado',
                'reason' => $availability['reason']
            ];
            return;
        }
        
        // Si el lote puede ser reasignado, continuar con el procesamiento normal
        Log::info("Lote disponible para reasignación - Continuando procesamiento", [
            'lot_number' => $lot->num_lot,
            'client_name' => $data['client_full_name'],
            'previous_status' => $availability['current_status']
        ]);
        
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
        
        // Actualizar status del lote con la nueva lógica
        $this->updateLotStatus($lot, $operationType, $availability['current_status']);
        
        $this->processed[] = [
            'row' => $rowNumber,
            'client' => $client->first_name . ' ' . $client->last_name,
            'lot' => $lot->num_lot,
            'advisor' => $advisor ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'No asignado',
            'operation_type' => $operationType,
            'reassigned' => $availability['current_status'] !== 'disponible'
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
        $docNumber = trim($data['client_doc_number'] ?? '');
        
        // Convert empty strings and '-' to NULL to avoid unique constraint violations
        if ($docNumber === '' || $docNumber === '-') {
            $docNumber = null;
        }
        
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
     * Ahora considera el estado anterior para mejor logging
     */
    private function updateLotStatus(Lot $lot, string $operationType, string $previousStatus = null): void
    {
        $newStatus = $operationType === 'contrato' ? 'vendido' : 'reservado';
        $previousStatus = $previousStatus ?? $lot->status;
        
        if ($lot->status !== $newStatus) {
            $lot->update(['status' => $newStatus]);
            Log::info("Status del lote {$lot->num_lot} actualizado", [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'operation_type' => $operationType,
                'lot_id' => $lot->lot_id
            ]);
        } else {
            Log::info("Status del lote {$lot->num_lot} ya está en {$newStatus}", [
                'current_status' => $lot->status,
                'operation_type' => $operationType,
                'lot_id' => $lot->lot_id
            ]);
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
     * Parsear fecha y retornar objeto Carbon
     */
    private function parseDateAsCarbon($date): Carbon
    {
        if (empty($date)) {
            return now();
        }
        
        Log::info("Parseando fecha como Carbon: {$date} (tipo: " . gettype($date) . ")");
        
        try {
            // Si es un número (Excel date serial)
            if (is_numeric($date) && $date > 0) {
                // Excel cuenta desde 1900-01-01, pero tiene un bug con 1900 como año bisiesto
                if ($date > 59) {
                    $parsed = Carbon::createFromDate(1900, 1, 1)->addDays($date - 2);
                } else {
                    $parsed = Carbon::createFromDate(1900, 1, 1)->addDays($date - 1);
                }
                Log::info("Fecha parseada desde serial como Carbon: " . $parsed->format('Y-m-d'));
                return $parsed;
            }
            
            // Convertir a string si no lo es
            $dateString = (string) $date;
            
            // Intentar varios formatos de fecha
            $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];
            
            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $dateString);
                    if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                        Log::info("Fecha parseada con formato {$format} como Carbon: " . $parsed->format('Y-m-d'));
                        return $parsed;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            // Intentar parsear con Carbon directamente
            $parsed = Carbon::parse($dateString);
            if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                Log::info("Fecha parseada con Carbon::parse como Carbon: " . $parsed->format('Y-m-d'));
                return $parsed;
            }
            
        } catch (Exception $e) {
            Log::warning("No se pudo parsear la fecha como Carbon: {$date} - Error: " . $e->getMessage());
        }
        
        Log::warning("Usando fecha actual como fallback para Carbon: {$date}");
        return now();
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

    /**
     * Verificar si un lote puede ser reasignado durante la importación
     * 
     * @param Lot $lot
     * @return array ['can_reassign' => bool, 'reason' => string, 'current_status' => string]
     */
    private function checkLotAvailability(Lot $lot): array
    {
        $currentStatus = $lot->status;
        
        Log::info("Verificando disponibilidad del lote", [
            'lot_number' => $lot->num_lot,
            'current_status' => $currentStatus
        ]);
        
        // Si el lote está disponible, puede ser asignado
        if ($currentStatus === 'disponible') {
            return [
                'can_reassign' => true,
                'reason' => 'Lote disponible para asignación',
                'current_status' => $currentStatus
            ];
        }
        
        // Si está vendido o reservado, verificar si tiene contratos/reservaciones activas
        if (in_array($currentStatus, ['vendido', 'reservado'])) {
            // Verificar reservaciones activas
            $activeReservations = $lot->reservations()
                ->whereIn('status', ['activa', 'confirmada'])
                ->count();
            
            // Verificar contratos activos
            $activeContracts = Contract::whereHas('reservation', function($query) use ($lot) {
                $query->where('lot_id', $lot->lot_id);
            })
            ->whereIn('status', ['vigente', 'activo'])
            ->count();
            
            Log::info("Verificando contratos y reservaciones activas", [
                'lot_number' => $lot->num_lot,
                'active_reservations' => $activeReservations,
                'active_contracts' => $activeContracts
            ]);
            
            // Si no tiene reservaciones ni contratos activos, puede ser reasignado
            if ($activeReservations === 0 && $activeContracts === 0) {
                return [
                    'can_reassign' => true,
                    'reason' => 'Lote marcado como ' . $currentStatus . ' pero sin contratos/reservaciones activas (venta cancelada)',
                    'current_status' => $currentStatus
                ];
            }
            
            // Si tiene contratos o reservaciones activas, no puede ser reasignado
            return [
                'can_reassign' => false,
                'reason' => 'Lote tiene ' . ($activeContracts > 0 ? 'contratos' : 'reservaciones') . ' activas',
                'current_status' => $currentStatus
            ];
        }
        
        // Para otros estados, no permitir reasignación por defecto
        return [
            'can_reassign' => false,
            'reason' => 'Estado del lote no permite reasignación: ' . $currentStatus,
            'current_status' => $currentStatus
        ];
    }

    /**
     * Procesar fila simplificada usando Lot Financial Templates
     */
    public function processRowSimplified(array $row, array $headers): array
    {
        try {
            $data = $this->mapRowDataSimplified($row, $headers);
            
            if ($this->isEmptyRowSimplified($data)) {
                return ['status' => 'skipped', 'message' => 'Fila vacía'];
            }
            
            $validation = $this->validateRowDataSimplified($data);
            if (!$validation['valid']) {
                return ['status' => 'error', 'message' => $validation['message']];
            }
            
            // Buscar o crear cliente
            $client = $this->findOrCreateClientSimplified($data);
            if (!$client) {
                return ['status' => 'error', 'message' => 'No se pudo crear o encontrar el cliente'];
            }
            
            // Buscar lote con template financiero
            $lot = $this->findLotWithFinancialTemplate($data);
            if (!$lot) {
                return ['status' => 'error', 'message' => 'Lote no encontrado o sin template financiero'];
            }
            
            // Verificar disponibilidad del lote
            $availability = $this->checkLotAvailability($lot);
            if (!$availability['can_reassign']) {
                return ['status' => 'error', 'message' => $availability['reason']];
            }
            
            // Logging de valores mapeados antes de decidir qué crear
            Log::info('processRowSimplified - Valores mapeados:', [
                'operation_type' => $data['operation_type'] ?? 'NO_DEFINIDO',
                'contract_status' => $data['contract_status'] ?? 'NO_DEFINIDO',
                'client_name' => $data['client_full_name'] ?? 'NO_DEFINIDO',
                'lot_number' => $data['lot_number'] ?? 'NO_DEFINIDO',
                'should_create_contract' => $this->shouldCreateContractSimplified($data)
            ]);
            
            $reservation = null;
            $contract = null;
            
            // Decidir si crear contrato o reservación (NO AMBOS)
            if ($this->shouldCreateContractSimplified($data)) {
                Log::info('processRowSimplified - Creando SOLO contrato directo (sin reservación)');
                
                // Buscar advisor
                $advisor = $this->findAdvisorSimplified($data);
                
                // Crear contrato directamente sin reservación temporal
                $contract = $this->createDirectContract($client, $lot, $data, $advisor);
                if (!$contract) {
                    Log::error('processRowSimplified - Error al crear contrato directo');
                    return ['status' => 'error', 'message' => 'No se pudo crear el contrato directo'];
                }
                
                Log::info('processRowSimplified - Contrato directo creado exitosamente', [
                    'contract_id' => $contract->contract_id,
                    'advisor_id' => $advisor ? $advisor->employee_id : null
                ]);
            } else {
                Log::info('processRowSimplified - Creando SOLO reservación (sin contrato)');
                $reservation = $this->createReservationSimplified($client, $lot, $data);
                if (!$reservation) {
                    return ['status' => 'error', 'message' => 'No se pudo crear la reservación'];
                }
                Log::info('processRowSimplified - Reservación creada exitosamente', ['reservation_id' => $reservation->reservation_id]);
            }
            
            // Actualizar estado del lote
            $this->updateLotStatus($lot, $data['operation_type'] ?? 'reserva');
            
            return [
                'status' => 'success',
                'message' => 'Procesado correctamente',
                'client_id' => $client->client_id,
                'lot_id' => $lot->lot_id,
                'reservation_id' => $reservation ? $reservation->reservation_id : null,
                'contract_id' => $contract ? $contract->contract_id : null
            ];
            
        } catch (Exception $e) {
            Log::error('Error procesando fila simplificada: ' . $e->getMessage(), [
                'row' => $row,
                'trace' => $e->getTraceAsString()
            ]);
            return ['status' => 'error', 'message' => 'Error interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mapear datos de fila simplificada
     */
    private function mapRowDataSimplified(array $row, array $headers): array
    {
        // Primero mapear los headers a los nombres de campo correctos
        $headerMap = $this->mapSimplifiedHeaders($headers);
        
        $data = [];
        
        // Usar el mapeo de headers para crear el array de datos con las claves correctas
        foreach ($headerMap as $fieldName => $index) {
            $value = $row[$index] ?? '';
            $data[$fieldName] = is_string($value) ? trim($value) : $value;
        }
        
        // También mantener los headers originales para compatibilidad con campos no mapeados
        foreach ($headers as $index => $header) {
            $headerUpper = trim(strtoupper($header));
            if (!isset($data[$headerUpper])) {
                $value = $row[$index] ?? '';
                $data[$headerUpper] = is_string($value) ? trim($value) : $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Verificar si la fila simplificada está vacía
     */
    private function isEmptyRowSimplified(array $data): bool
    {
        $essentialFields = ['client_full_name', 'lot_number', 'lot_manzana'];
        
        foreach ($essentialFields as $field) {
            if (!empty($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validar datos de fila simplificada
     */
    private function validateRowDataSimplified(array $data): array
    {
        $errors = [];
        
        // Validar campos obligatorios
        $requiredFields = [
            'client_full_name' => 'Nombres completos del cliente',
            'lot_number' => 'Número de lote',
            'lot_manzana' => 'Manzana del lote'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = "Campo obligatorio faltante: {$label}";
            }
        }
        
        // Validar tipo de documento si se proporciona
        if (!empty($data['cliente_tipo_doc']) && !in_array($data['cliente_tipo_doc'], ['DNI', 'CE', 'RUC', 'PASAPORTE'])) {
            $errors[] = 'Tipo de documento inválido';
        }
        
        // Validar tipo de operación
        if (!empty($data['operation_type']) && !in_array(strtolower($data['operation_type']), ['reserva', 'venta', 'contrato'])) {
            $errors[] = 'Tipo de operación inválido';
        }
        
        return [
            'valid' => empty($errors),
            'message' => implode(', ', $errors)
        ];
    }
    
    /**
     * Crear contrato directo sin reservación
     * TODOS los datos financieros vienen directamente del LotFinancialTemplate
     */
    private function createDirectContract($client, $lot, array $data, $advisor = null)
    {
        try {
            // Obtener template financiero del lote - ES OBLIGATORIO
            $financialTemplate = $lot->financialTemplate;
            if (!$financialTemplate) {
                throw new Exception("Lote {$lot->lot_id} no tiene template financiero. Todos los datos financieros deben venir del LotFinancialTemplate.");
            }

            // Usar ÚNICAMENTE los valores directos del template financiero - SIN cálculos ni fallbacks
            $totalPrice = $financialTemplate->precio_venta;
            $downPayment = $financialTemplate->cuota_inicial;
            $financingAmount = $financialTemplate->precio_venta - $financialTemplate->cuota_inicial;
            
            // Buscar el primer installment válido (mayor a 0) - LÓGICA LOT-ESPECÍFICA
            $monthlyPayment = 0;
            $termMonths = 0;
            $interestRate = 0; // Interest rate siempre es 0 como especificó el usuario
            $hasInstallments = false;
            
            // Priorizar installments_40, luego installments_44, luego installments_24
            if ($financialTemplate->installments_40 > 0) {
                $monthlyPayment = $financialTemplate->installments_40;
                $termMonths = 40;
                $hasInstallments = true;
            } elseif ($financialTemplate->installments_44 > 0) {
                $monthlyPayment = $financialTemplate->installments_44;
                $termMonths = 44;
                $hasInstallments = true;
            } elseif ($financialTemplate->installments_24 > 0) {
                $monthlyPayment = $financialTemplate->installments_24;
                $termMonths = 24;
                $hasInstallments = true;
            } elseif ($financialTemplate->installments_55 > 0) {
                $monthlyPayment = $financialTemplate->installments_55;
                $termMonths = 55;
                $hasInstallments = true;
            }
            
            // Si no hay installments válidos, es un contrato sin financiamiento (pago de contado)
            if (!$hasInstallments) {
                Log::info('createDirectContract - Contrato sin financiamiento detectado', [
                    'lot_id' => $lot->lot_id,
                    'reason' => 'Todos los installments_XX están en 0 - Pago de contado',
                    'installments_check' => [
                        'installments_24' => $financialTemplate->installments_24,
                        'installments_40' => $financialTemplate->installments_40,
                        'installments_44' => $financialTemplate->installments_44,
                        'installments_55' => $financialTemplate->installments_55
                    ]
                ]);
                
                // Ajustar valores para contrato sin financiamiento
                $financingAmount = 0;
                $monthlyPayment = 0;
                $termMonths = 0;
                $interestRate = 0;
            }
            
            Log::info('createDirectContract - Usando valores DIRECTOS del template financiero', [
                'template_id' => $financialTemplate->id,
                'lot_id' => $lot->lot_id,
                'precio_venta' => $totalPrice,
                'cuota_inicial' => $downPayment,
                'financing_amount' => $financingAmount,
                'monthly_payment_found' => $monthlyPayment,
                'term_months_selected' => $termMonths,
                'interest_rate' => $interestRate,
                'has_installments' => $hasInstallments,
                'financing_type' => $hasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING',
                'installments_available' => [
                    'installments_24' => $financialTemplate->installments_24,
                    'installments_40' => $financialTemplate->installments_40,
                    'installments_44' => $financialTemplate->installments_44,
                    'installments_55' => $financialTemplate->installments_55
                ]
            ]);



            // Generar número de contrato único
            $contractNumber = $this->generateContractNumber();
            
            // Preparar datos del contrato
            $contractData = [
                'client_id' => $client->client_id,
                'lot_id' => $lot->lot_id,
                'advisor_id' => $advisor ? $advisor->employee_id : null,
                'contract_number' => $contractNumber,
                'reservation_id' => null, // Sin reservación para contratos directos
                'sign_date' => $this->parseDate($data['fecha_venta'] ?? $data['sign_date'] ?? null), // Usar fecha del Excel
                'total_price' => $totalPrice,
                'down_payment' => $downPayment,
                'financing_amount' => $financingAmount,
                'monthly_payment' => $monthlyPayment,
                'term_months' => $termMonths,
                'interest_rate' => $interestRate,
                'status' => 'vigente', // Usar valor válido del enum
                'currency' => 'PEN', // Agregar moneda requerida
                'financing_type' => $hasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING' // CORREGIR: Agregar financing_type
            ];

            Log::info('createDirectContract - Template financiero encontrado', [
                'lot_id' => $lot->lot_id,
                'template_id' => $financialTemplate->id ?? 'N/A',
                'down_payment' => $downPayment,
                'total_price' => $totalPrice,
                'installments_count' => $termMonths,
                'financing_type' => $hasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING',
                'financing_amount_final' => $financingAmount
            ]);
            
            Log::info('createDirectContract - Creando contrato con datos:', $contractData);

            // Crear el contrato
            $contract = Contract::create($contractData);

            if ($contract) {
                Log::info('createDirectContract - Contrato creado exitosamente', [
                    'contract_id' => $contract->contract_id,
                    'client_id' => $contract->client_id,
                    'lot_id' => $contract->lot_id,
                    'advisor_id' => $contract->advisor_id,
                    'financing_amount' => $contract->financing_amount,
                    'financing_type' => $contract->financing_amount > 0 ? 'WITH_FINANCING' : 'WITHOUT_FINANCING'
                ]);
            }

            return $contract;

        } catch (Exception $e) {
            Log::error('createDirectContract - Error: ' . $e->getMessage(), [
                'client_id' => $client->client_id ?? null,
                'lot_id' => $lot->lot_id ?? null,
                'advisor_id' => $advisor ? $advisor->employee_id : null,
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Separar nombres completos en nombres y apellidos
     * Asume que las últimas dos palabras son siempre los apellidos
     */
    public function parseClientName(string $fullName): array
    {
        $fullName = trim($fullName);
        $words = explode(' ', $fullName);
        $words = array_filter($words); // Remover espacios vacíos
        $words = array_values($words); // Reindexar
        
        $wordCount = count($words);
        
        if ($wordCount <= 2) {
            // Si hay 2 palabras o menos, la primera es nombre y la última apellido
            return [
                'first_name' => $words[0] ?? '',
                'last_name' => $words[1] ?? $words[0] ?? ''
            ];
        }
        
        // Si hay más de 2 palabras, las últimas 2 son apellidos
        $lastNames = array_slice($words, -2);
        $firstNames = array_slice($words, 0, -2);
        
        return [
            'first_name' => implode(' ', $firstNames),
            'last_name' => implode(' ', $lastNames)
        ];
    }

    /**
     * Buscar o crear cliente simplificado
     */
    private function findOrCreateClientSimplified(array $data): ?Client
    {
        try {
            // Separar nombres y apellidos del campo único
            $parsedName = $this->parseClientName($data['client_full_name'] ?? '');
            
            // Validar que el documento no esté vacío si se proporciona
            $docNumber = trim($data['cliente_num_doc'] ?? '');
            if ($docNumber === '' || $docNumber === '-') {
                $docNumber = null;
            }
            
            $query = Client::query();
            
            // Buscar por documento si se proporciona y no está vacío
            if (!empty($docNumber)) {
                $query->where('doc_number', $docNumber);
            } else {
                // Buscar por nombre completo usando los nombres separados
                $query->where('first_name', 'LIKE', '%' . $parsedName['first_name'] . '%')
                      ->where('last_name', 'LIKE', '%' . $parsedName['last_name'] . '%');
            }
            
            $client = $query->first();
            
            if ($client) {
                // Actualizar información si es necesario
                $this->updateClientInfoSimplified($client, $data);
                return $client;
            }
            
            // Validar datos mínimos requeridos para crear cliente
            if (empty($parsedName['first_name']) || empty($parsedName['last_name'])) {
                Log::warning('Datos insuficientes para crear cliente', [
                    'client_full_name' => $data['client_full_name'] ?? '',
                    'parsed_name' => $parsedName
                ]);
                return null;
            }
            
            // Crear nuevo cliente con nombres separados
            return Client::create([
                'first_name' => $parsedName['first_name'],
                'last_name' => $parsedName['last_name'],
                'doc_type' => $data['cliente_tipo_doc'] ?? 'DNI',
                'doc_number' => $docNumber, // Usar el valor validado (puede ser null)
                'primary_phone' => !empty($data['cliente_telefono_1']) ? $data['cliente_telefono_1'] : null,
                'secondary_phone' => !empty($data['cliente_telefono_2']) ? $data['cliente_telefono_2'] : null,
                'type' => 'client',
                'observations' => $data['observaciones'] ?? null
            ]);
            
        } catch (Exception $e) {
            Log::error('Error creando cliente simplificado: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Actualizar información del cliente simplificado
     */
    private function updateClientInfoSimplified(Client $client, array $data): void
    {
        $updates = [];
        
        // Validar y actualizar teléfono primario
        $primaryPhone = !empty($data['cliente_telefono_1']) ? $data['cliente_telefono_1'] : null;
        if ($primaryPhone !== null && $client->primary_phone !== $primaryPhone) {
            $updates['primary_phone'] = $primaryPhone;
        }
        
        // Validar y actualizar teléfono secundario
        $secondaryPhone = !empty($data['cliente_telefono_2']) ? $data['cliente_telefono_2'] : null;
        if ($secondaryPhone !== null && $client->secondary_phone !== $secondaryPhone) {
            $updates['secondary_phone'] = $secondaryPhone;
        }
        
        // Validar y actualizar email
        if (!empty($data['cliente_email']) && $client->email !== $data['cliente_email']) {
            $updates['email'] = $data['cliente_email'];
        }
        
        if (!empty($updates)) {
            $client->update($updates);
        }
    }
    
    /**
     * Buscar lote con template financiero
     */
    private function findLotWithFinancialTemplate(array $data): ?Lot
    {
        try {
            // Convertir nombre de manzana a ID con mapeo
            $manzanaName = $data['lot_manzana'];
            $mappedManzanaName = $this->mapManzanaName($manzanaName);
            
            $manzana = \Modules\Inventory\Models\Manzana::where('name', $mappedManzanaName)->first();
            
            if (!$manzana) {
                Log::warning('Manzana no encontrada', [
                    'manzana_name_original' => $manzanaName,
                    'manzana_name_mapped' => $mappedManzanaName,
                    'available_manzanas' => \Modules\Inventory\Models\Manzana::pluck('name')->toArray()
                ]);
                return null;
            }
            
            $lot = Lot::where('num_lot', $data['lot_number'])
                     ->where('manzana_id', $manzana->manzana_id)
                     ->first();
            
            if (!$lot) {
                Log::warning('Lote no encontrado', [
                    'numero' => $data['lot_number'],
                    'manzana_name' => $manzanaName,
                    'manzana_id' => $manzana->manzana_id
                ]);
                return null;
            }
            
            // Verificar que tenga template financiero
            if (!$lot->financialTemplate) {
                Log::warning('Lote sin template financiero', [
                    'lot_id' => $lot->lot_id,
                    'numero' => $lot->num_lot,
                    'manzana' => $lot->manzana
                ]);
                return null;
            }
            
            return $lot;
            
        } catch (Exception $e) {
            Log::error('Error buscando lote con template: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mapear nombres de manzana del Excel a nombres de la base de datos
     */
    private function mapManzanaName(string $excelManzanaName): string
    {
        // Mapeo de nombres del Excel a nombres de la base de datos
        // Basado en manzanas disponibles: A, D, E, F, G, H, I, J
        $mapping = [
            // Mapeo por nombres completos
            'manzana 1' => 'A',
            'manzana 2' => 'E',
            'manzana 3' => 'F',
            'manzana 4' => 'G',
            'manzana 5' => 'H',
            'manzana 6' => 'I',
            'manzana 7' => 'J',
            'manzana 8' => 'D',
            // Mapeo por números directos
            '1' => 'A',
            '2' => 'E',
            '3' => 'F',
            '4' => 'G',
            '5' => 'H',
            '6' => 'I',
            '7' => 'J',
            '8' => 'D',
            // Mapeo por letras (caso directo)
            'a' => 'A',
            'd' => 'D',
            'e' => 'E',
            'f' => 'F',
            'g' => 'G',
            'h' => 'H',
            'i' => 'I',
            'j' => 'J',
            // Mapeo adicional para casos especiales
            'x' => 'A', // Mapear 'X' a 'A' como fallback
            'mz1' => 'A',
            'mz2' => 'E',
            'mz3' => 'F',
            'mz4' => 'G',
            'mz5' => 'H',
            'mz6' => 'I',
            'mz7' => 'J',
            'mz8' => 'D'
        ];
        
        $normalizedName = strtolower(trim($excelManzanaName));
        
        if (isset($mapping[$normalizedName])) {
            Log::info('mapManzanaName - Mapeo aplicado', [
                'original' => $excelManzanaName,
                'normalized' => $normalizedName,
                'mapped' => $mapping[$normalizedName]
            ]);
            return $mapping[$normalizedName];
        }
        
        // Si el valor ya es una letra válida (A-J), devolverlo en mayúscula
        $upperName = strtoupper($normalizedName);
        if (in_array($upperName, ['A', 'D', 'E', 'F', 'G', 'H', 'I', 'J'])) {
            Log::info('mapManzanaName - Nombre válido encontrado', [
                'original' => $excelManzanaName,
                'valid_name' => $upperName
            ]);
            return $upperName;
        }
        
        // Si no hay mapeo, usar 'A' como fallback y registrar warning
        Log::warning('mapManzanaName - Nombre no reconocido, usando fallback A', [
            'original' => $excelManzanaName,
            'normalized' => $normalizedName,
            'available_manzanas' => ['A', 'D', 'E', 'F', 'G', 'H', 'I', 'J']
        ]);
        return 'A';
    }
    
    /**
     * Crear reservación simplificada
     */
    private function createReservationSimplified(Client $client, Lot $lot, array $data): ?Reservation
    {
        try {
            // Buscar asesor
            $advisor = $this->findAdvisorSimplified($data);
            
            // Si no se encuentra asesor, usar el primer asesor disponible
            if (!$advisor) {
                $advisor = Employee::where('employee_type', 'asesor_inmobiliario')->first();
                if (!$advisor) {
                    Log::error('No hay asesores disponibles en el sistema');
                    return null;
                }
            }
            
            $reservationDate = $this->parseDateAsCarbon($data['fecha_venta'] ?? now());
            $expirationDate = $reservationDate->copy()->addDays(30); // 30 días de vigencia por defecto
            
            // Logging detallado para debugging
            Log::info('createReservationSimplified - Valores antes de crear reservación:', [
                'lot_id' => $lot->lot_id ?? 'NULL',
                'lot_num_lot' => $lot->num_lot ?? 'NULL',
                'advisor_id' => $advisor->employee_id ?? 'NULL',
                'advisor_name' => ($advisor && $advisor->user) ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'NULL',
                'client_id' => $client->client_id ?? 'NULL',
                'deposit_amount' => $lot->financialTemplate->initial_payment ?? 0
            ]);
            
            return Reservation::create([
                'client_id' => $client->client_id,
                'lot_id' => $lot->lot_id,
                'advisor_id' => $advisor->employee_id,
                'reservation_date' => $reservationDate->format('Y-m-d'),
                'expiration_date' => $expirationDate->format('Y-m-d'),
                'sale_date' => $reservationDate->format('Y-m-d'),
                'deposit_amount' => $lot->financialTemplate->initial_payment ?? 0,
                'reference' => 'IMP-' . date('YmdHis') . '-' . $lot->num_lot,
                'status' => 'pendiente_pago',
                'observations' => $data['observaciones'] ?? null
            ]);
            
        } catch (Exception $e) {
            Log::error('Error creando reservación simplificada: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Buscar asesor simplificado con fallback
     */
    private function findAdvisorSimplified(array $data): ?Employee
    {
        if (empty($data['asesor_nombre']) && empty($data['asesor_codigo']) && empty($data['asesor_email'])) {
            Log::warning('findAdvisorSimplified - No se proporcionaron datos de asesor, usando fallback');
            return $this->getDefaultAdvisor();
        }
        
        // Buscar por código primero
        if (!empty($data['asesor_codigo'])) {
            $advisor = Employee::with('user')->where('employee_code', $data['asesor_codigo'])->first();
            if ($advisor) {
                Log::info('findAdvisorSimplified - Asesor encontrado por código', [
                    'employee_code' => $data['asesor_codigo'],
                    'advisor_id' => $advisor->employee_id
                ]);
                return $advisor;
            }
        }
        
        // Buscar por email del usuario asociado
        if (!empty($data['asesor_email'])) {
            $advisor = Employee::with('user')->whereHas('user', function($q) use ($data) {
                $q->where('email', $data['asesor_email']);
            })->first();
            if ($advisor) {
                Log::info('findAdvisorSimplified - Asesor encontrado por email', [
                    'asesor_email' => $data['asesor_email'],
                    'advisor_id' => $advisor->employee_id
                ]);
                return $advisor;
            }
        }
        
        // Buscar por nombre del usuario asociado usando CONCAT
        if (!empty($data['asesor_nombre'])) {
            $advisor = Employee::with('user')->whereHas('user', function($q) use ($data) {
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $data['asesor_nombre'] . '%'])
                  ->orWhere('first_name', 'LIKE', '%' . $data['asesor_nombre'] . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $data['asesor_nombre'] . '%');
            })->first();
            if ($advisor) {
                Log::info('findAdvisorSimplified - Asesor encontrado por nombre', [
                    'asesor_nombre' => $data['asesor_nombre'],
                    'advisor_id' => $advisor->employee_id
                ]);
                return $advisor;
            }
        }
        
        // Si no se encuentra, usar asesor por defecto
        Log::warning('findAdvisorSimplified - Asesor no encontrado, usando fallback', [
            'asesor_nombre' => $data['asesor_nombre'] ?? 'N/A',
            'asesor_codigo' => $data['asesor_codigo'] ?? 'N/A',
            'asesor_email' => $data['asesor_email'] ?? 'N/A'
        ]);
        
        return $this->getDefaultAdvisor();
    }
    
    /**
     * Obtener asesor por defecto para casos de fallback
     */
    private function getDefaultAdvisor(): ?Employee
    {
        // Buscar el primer asesor inmobiliario disponible
        $defaultAdvisor = Employee::with('user')
            ->where('employee_type', 'asesor_inmobiliario')
            ->first();
            
        if ($defaultAdvisor) {
            Log::info('getDefaultAdvisor - Usando asesor por defecto', [
                'default_advisor_id' => $defaultAdvisor->employee_id,
                'default_advisor_code' => $defaultAdvisor->employee_code,
                'default_advisor_name' => $defaultAdvisor->user ? 
                    $defaultAdvisor->user->first_name . ' ' . $defaultAdvisor->user->last_name : 'N/A'
            ]);
        } else {
            Log::error('getDefaultAdvisor - No se encontró ningún asesor inmobiliario en el sistema');
        }
        
        return $defaultAdvisor;
    }
    
    /**
     * Determinar si se debe crear contrato
     */
    private function shouldCreateContractSimplified(array $data): bool
    {
        $tipoOperacion = strtolower($data['operation_type'] ?? '');
        $estadoContrato = strtolower($data['contract_status'] ?? '');
        
        // Logging detallado para debugging
        Log::info('shouldCreateContractSimplified - Valores recibidos:', [
            'operation_type_original' => $data['operation_type'] ?? 'NO_DEFINIDO',
            'operation_type_lowercase' => $tipoOperacion,
            'contract_status_original' => $data['contract_status'] ?? 'NO_DEFINIDO',
            'contract_status_lowercase' => $estadoContrato,
            'all_data_keys' => array_keys($data),
            'operation_type_in_venta_contrato' => in_array($tipoOperacion, ['venta', 'contrato']),
            'contract_status_in_valid_states' => in_array($estadoContrato, ['vigente', 'activo', 'firmado'])
        ]);
        
        // Lógica más permisiva: permitir contratos con estado null/vacío
        // y también procesar 'reserva' como contrato válido
        $shouldCreate = in_array($tipoOperacion, ['venta', 'contrato', 'reserva']) || 
                       in_array($estadoContrato, ['vigente', 'activo', 'firmado']) ||
                       empty($estadoContrato) || is_null($data['contract_status'] ?? null);
        
        Log::info('shouldCreateContractSimplified - Resultado:', [
            'should_create_contract' => $shouldCreate,
            'reason' => $shouldCreate ? 'Cumple condiciones para crear contrato (lógica permisiva)' : 'No cumple condiciones'
        ]);
        
        return $shouldCreate;
    }
    
    /**
     * Crear contrato desde template financiero
     */
    private function createContractFromTemplate(Reservation $reservation, Lot $lot, array $data): ?Contract
    {
        try {
            $template = $lot->financialTemplate;
            if (!$template) {
                Log::error('Template financiero no encontrado para el lote', ['lot_id' => $lot->lot_id]);
                return null;
            }
            
            // Usar métodos del template para obtener valores correctos
            $totalPrice = $template->getEffectivePrice(); // precio_venta o precio_lista
            $downPayment = $template->cuota_inicial ?? 0;
            $financingAmount = $template->getFinancingAmount();
            $balloonPayment = $template->cuota_balon ?? 0;
            
            // Determinar cuotas mensuales basado en installments disponibles
            $monthlyPayment = 0;
            $termMonths = 0;
            $interestRate = 0;
            
            // Priorizar installments_40, luego installments_44, luego installments_24
            if ($template->installments_40 > 0) {
                $monthlyPayment = $template->installments_40;
                $termMonths = 40;
                $interestRate = 0; // Sin interés aplicado por el momento
            } elseif ($template->installments_44 > 0) {
                $monthlyPayment = $template->installments_44;
                $termMonths = 44;
                $interestRate = 0; // Sin interés aplicado por el momento
            } elseif ($template->installments_24 > 0) {
                $monthlyPayment = $template->installments_24;
                $termMonths = 24;
                $interestRate = 0; // Sin interés aplicado por el momento
            }
            
            // Determinar si es financiado
            $isFinanced = $monthlyPayment > 0 && $termMonths > 0;
            
            $contractData = [
                'reservation_id' => $reservation->reservation_id,
                'contract_number' => $this->generateContractNumber(),
                'sign_date' => $this->parseDate($data['fecha_venta'] ?? now()),
                'total_price' => $totalPrice,
                'down_payment' => $downPayment,
                'financing_amount' => $isFinanced ? $financingAmount : 0,
                'monthly_payment' => $isFinanced ? $monthlyPayment : 0,
                'term_months' => $isFinanced ? $termMonths : 0,
                'interest_rate' => $isFinanced ? $interestRate : 0,
                'balloon_payment' => $balloonPayment,
                'funding' => $template->bono_bpp ?? 0,
                'bpp' => $template->bono_bpp ?? 0,
                'bfh' => 0, // Campo BFH - valor por defecto
                'initial_quota' => $downPayment,
                'currency' => 'USD'
                // Omitir status completamente ya que causa truncamiento incluso con valor vacío
            ];
            
            Log::info('createContractFromTemplate - Debugging monthly_payment y valores del contrato:', [
                'lot_id' => $lot->lot_id,
                'template_data' => [
                    'precio_lista' => $template->precio_lista,
                    'precio_venta' => $template->precio_venta,
                    'cuota_inicial' => $template->cuota_inicial,
                    'installments_24' => $template->installments_24,
                    'installments_40' => $template->installments_40,
                    'installments_44' => $template->installments_44,
                    'cuota_balon' => $template->cuota_balon
                ],
                'calculated_values' => [
                    'monthly_payment_calculated' => $monthlyPayment,
                    'term_months_selected' => $termMonths,
                    'interest_rate_set' => $interestRate,
                    'total_price' => $totalPrice,
                    'financing_amount' => $financingAmount,
                    'is_financed' => $isFinanced
                ],
                'contract_data' => $contractData
            ]);
            
            Log::info('Intentando crear contrato con Contract::create()');
            
            try {
                $contract = Contract::create($contractData);
                
                if (!$contract) {
                    Log::error('Contract::create() retornó null o false');
                    return null;
                }
                
                Log::info('Contrato creado exitosamente:', ['contract_id' => $contract->contract_id]);
            } catch (\Exception $createException) {
                Log::error('Excepción al crear contrato:', [
                    'message' => $createException->getMessage(),
                    'file' => $createException->getFile(),
                    'line' => $createException->getLine(),
                    'contract_data' => $contractData
                ]);
                return null;
            }
            
            // Generar cronograma de pagos si es financiado
            if ($isFinanced && $contract) {
                $paymentScheduleService = app(PaymentScheduleService::class);
                $paymentScheduleService->generateIntelligentSchedule($contract);
            }
            
            return $contract;
            
        } catch (Exception $e) {
            Log::error('Error creando contrato desde template: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}