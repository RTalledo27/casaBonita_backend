<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Sales\Services\ContractImportService;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Users\Models\Client;
use Modules\HumanResources\Models\Employee;
use Illuminate\Support\Facades\Log;

class ContractErrorDebugger
{
    private $importService;
    
    public function __construct()
    {
        $this->importService = new ContractImportService();
    }
    
    public function debugContractErrors($filePath)
    {
        echo "=== DEBUG DE ERRORES DE CONTRATO ===\n";
        echo "Archivo: {$filePath}\n\n";
        
        // Cargar archivo Excel
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (empty($rows)) {
            echo "ERROR: Archivo vacío\n";
            return;
        }
        
        $headers = array_shift($rows);
        echo "Headers encontrados: " . implode(', ', $headers) . "\n\n";
        
        // Analizar primeras 10 filas con errores
        $errorCount = 0;
        $successCount = 0;
        
        foreach ($rows as $index => $row) {
            if ($errorCount >= 10) break; // Limitar a 10 errores para análisis
            
            $rowNumber = $index + 2; // +2 porque empezamos desde 0 y saltamos headers
            
            echo "\n--- FILA {$rowNumber} ---\n";
            
            // Mapear datos
            $data = $this->mapRowData($row, $headers);
            
            // Verificar si debe crear contrato
            $shouldCreate = $this->shouldCreateContractSimplified($data);
            echo "¿Debe crear contrato?: " . ($shouldCreate ? 'SÍ' : 'NO') . "\n";
            
            if (!$shouldCreate) {
                echo "RAZÓN: operation_type='" . ($data['operation_type'] ?? 'N/A') . "', contract_status='" . ($data['contract_status'] ?? 'N/A') . "'\n";
                continue;
            }
            
            // Verificar cliente
            echo "Cliente: " . ($data['cliente_nombres'] ?? 'N/A') . "\n";
            
            // Verificar lote y manzana
            echo "Lote: " . ($data['lot_number'] ?? 'N/A') . ", Manzana: " . ($data['lot_manzana'] ?? 'N/A') . "\n";
            
            // Verificar manzana existe
            $manzanaName = $this->mapManzanaName($data['lot_manzana'] ?? '');
            $manzana = Manzana::where('name', $manzanaName)->first();
            
            if (!$manzana) {
                echo "ERROR: Manzana '{$manzanaName}' no encontrada\n";
                echo "Manzanas disponibles: " . implode(', ', Manzana::pluck('name')->toArray()) . "\n";
                $errorCount++;
                continue;
            }
            
            echo "Manzana encontrada: ID {$manzana->manzana_id}, Nombre: {$manzana->name}\n";
            
            // Verificar lote existe
            $lot = Lot::where('num_lot', $data['lot_number'] ?? '')
                     ->where('manzana_id', $manzana->manzana_id)
                     ->first();
            
            if (!$lot) {
                echo "ERROR: Lote {$data['lot_number']} no encontrado en manzana {$manzana->name}\n";
                echo "Lotes disponibles en manzana {$manzana->name}: " . 
                     implode(', ', Lot::where('manzana_id', $manzana->manzana_id)->pluck('num_lot')->toArray()) . "\n";
                $errorCount++;
                continue;
            }
            
            echo "Lote encontrado: ID {$lot->lot_id}\n";
            
            // Verificar template financiero
            $financialTemplate = $lot->financialTemplate;
            if (!$financialTemplate) {
                echo "ERROR: Lote {$lot->lot_id} no tiene template financiero\n";
                $errorCount++;
                continue;
            }
            
            echo "Template financiero: ID {$financialTemplate->id}\n";
            echo "Precio venta: {$financialTemplate->precio_venta}\n";
            echo "Cuota inicial: {$financialTemplate->cuota_inicial}\n";
            echo "Cuota 24 meses: {$financialTemplate->installments_24}\n";
            
            // Verificar asesor
            $advisor = $this->findAdvisorSimplified($data);
            if ($advisor) {
                echo "Asesor encontrado: ID {$advisor->employee_id}\n";
            } else {
                echo "ADVERTENCIA: No se encontró asesor específico, se usará uno por defecto\n";
            }
            
            // Simular creación de cliente
            try {
                $parsedName = $this->parseClientName($data['cliente_nombres'] ?? '');
                echo "Nombre parseado: '{$parsedName['first_name']}' '{$parsedName['last_name']}'\n";
                
                if (empty($parsedName['first_name']) || empty($parsedName['last_name'])) {
                    echo "ERROR: Nombres insuficientes para crear cliente\n";
                    $errorCount++;
                    continue;
                }
                
                echo "Cliente puede ser creado correctamente\n";
                $successCount++;
                
            } catch (Exception $e) {
                echo "ERROR creando cliente: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
        
        echo "\n=== RESUMEN ===\n";
        echo "Filas que pueden procesarse exitosamente: {$successCount}\n";
        echo "Filas con errores analizadas: {$errorCount}\n";
    }
    
    private function mapRowData($row, $headers)
    {
        $headerMap = [
            'CLIENTE_NOMBRE_COMPLETO' => 'cliente_nombres',
            'CLIENTE_TIPO_DOC' => 'cliente_tipo_doc', 
            'CLIENTE_NUM_DOC' => 'cliente_num_doc',
            'CLIENTE_TELEFONO_1' => 'cliente_telefono_1',
            'CLIENTE_EMAIL' => 'cliente_email',
            'LOTE_NUMERO' => 'lot_number',
            'LOTE_MANZANA' => 'lot_manzana',
            'TIPO_OPERACION' => 'operation_type',
            'ESTADO_CONTRATO' => 'contract_status',
            'FECHA_VENTA' => 'fecha_venta',
            'ASESOR_NOMBRE' => 'asesor_nombre',
            'ASESOR_CODIGO' => 'asesor_codigo',
            'ASESOR_EMAIL' => 'asesor_email',
            'OBSERVACIONES' => 'observaciones'
        ];
        
        $data = [];
        foreach ($headers as $index => $header) {
            $headerUpper = trim(strtoupper($header));
            $fieldName = $headerMap[$headerUpper] ?? strtolower(str_replace(' ', '_', $headerUpper));
            $value = $row[$index] ?? '';
            $data[$fieldName] = is_string($value) ? trim($value) : $value;
        }
        
        return $data;
    }
    
    private function shouldCreateContractSimplified($data)
    {
        $operationType = strtolower(trim($data['operation_type'] ?? ''));
        $contractStatus = strtolower(trim($data['contract_status'] ?? ''));
        
        return ($operationType === 'venta' || $operationType === 'contrato') ||
               ($contractStatus === 'vigente' || $contractStatus === 'activo' || $contractStatus === 'firmado');
    }
    
    private function mapManzanaName($excelManzanaName)
    {
        $mapping = [
            'manzana 1' => 'A', '1' => 'A',
            'manzana 2' => 'E', '2' => 'E', 
            'manzana 3' => 'F', '3' => 'F',
            'manzana 4' => 'G', '4' => 'G',
            'manzana 5' => 'H', '5' => 'H',
            'manzana 6' => 'I', '6' => 'I',
            'manzana 7' => 'J', '7' => 'J',
            'manzana 8' => 'D', '8' => 'D'
        ];
        
        $normalizedName = strtolower(trim($excelManzanaName));
        return $mapping[$normalizedName] ?? $excelManzanaName;
    }
    
    private function findAdvisorSimplified($data)
    {
        if (empty($data['asesor_nombre']) && empty($data['asesor_codigo']) && empty($data['asesor_email'])) {
            return null;
        }
        
        if (!empty($data['asesor_codigo'])) {
            return Employee::where('employee_code', $data['asesor_codigo'])->first();
        }
        
        if (!empty($data['asesor_email'])) {
            return Employee::whereHas('user', function($q) use ($data) {
                $q->where('email', $data['asesor_email']);
            })->first();
        }
        
        if (!empty($data['asesor_nombre'])) {
            return Employee::whereHas('user', function($q) use ($data) {
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $data['asesor_nombre'] . '%']);
            })->first();
        }
        
        return null;
    }
    
    private function parseClientName($fullName)
    {
        $fullName = trim($fullName);
        $words = explode(' ', $fullName);
        $words = array_filter($words);
        $words = array_values($words);
        
        $wordCount = count($words);
        
        if ($wordCount <= 2) {
            return [
                'first_name' => $words[0] ?? '',
                'last_name' => $words[1] ?? $words[0] ?? ''
            ];
        }
        
        $lastNames = array_slice($words, -2);
        $firstNames = array_slice($words, 0, -2);
        
        return [
            'first_name' => implode(' ', $firstNames),
            'last_name' => implode(' ', $lastNames)
        ];
    }
}

// Ejecutar debug
if ($argc < 2) {
    echo "Uso: php debug_contract_errors.php <archivo_excel>\n";
    exit(1);
}

$filePath = $argv[1];
if (!file_exists($filePath)) {
    echo "ERROR: Archivo {$filePath} no existe\n";
    exit(1);
}

$debugger = new ContractErrorDebugger();
$debugger->debugContractErrors($filePath);