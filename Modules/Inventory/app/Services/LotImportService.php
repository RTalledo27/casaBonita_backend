<?php

namespace Modules\Inventory\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Exception;

class LotImportService
{
    protected array $requiredHeaders = [
        'MZNA', 'LOTE', 'ÁREA LOTE', 'UBICACIÓN', 'PRECIO m2', 
        'PRECIO LISTA', 'DSCTO', 'PRECIO VENTA', 'CUOTA BALON', 
        'BONO BPP', 'CUOTA INICIAL', 'CI FRACC'
    ];

    /**
     * Procesa el archivo Excel y realiza la importación de lotes
     */
    public function processExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Extraer headers y fila de valores de financiamiento
        $headers = $rows[0];
        $financingValuesRow = isset($rows[1]) ? $rows[1] : [];
        
        // Extraer reglas de financiamiento y mapeo de columnas desde headers y fila 2
        $financingData = $this->extractFinancingRules($headers, $financingValuesRow);
        $financingRules = $financingData['rules'];
        $columnMapping = $financingData['column_mapping'];
        
        // Validar headers base (sin incluir manzanas dinámicas)
        $this->validateHeaders($headers);
        
        // Remover las primeras dos filas (headers y valores de financiamiento)
        array_shift($rows); // Remover headers
        array_shift($rows); // Remover fila de valores de financiamiento

        $results = [
            'total' => count($rows),
            'success' => 0,
            'errors' => [],
            'financing_rules' => $financingRules,
            'column_mapping' => $columnMapping
        ];

        DB::beginTransaction();
        try {
            // Crear/actualizar reglas de financiamiento por manzana
            $this->updateManzanaFinancingRules($financingRules);
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos en fila 2
                
                try {
                    $this->processRow($row, $headers, $financingRules, $columnMapping);
                    $results['success']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Fila {$rowNumber}: {$e->getMessage()}";
                }
            }
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        return $results;
    }

    /**
     * Extrae las reglas de financiamiento dinámicamente desde los headers y fila 2 del Excel
     * Detecta automáticamente cualquier columna de manzana (A-Z) y lee sus valores de financiamiento
     */
    protected function extractFinancingRules(array $headerRow, array $financingValuesRow): array
    {
        $financingRules = [];
        $columnMapping = [];
        
        // Detectar automáticamente columnas de manzanas (letras A-Z)
        foreach ($headerRow as $index => $header) {
            // Verificar si el header es una sola letra (manzana)
            if (preg_match('/^[A-Z]$/', $header)) {
                $manzanaLetter = $header;
                $columnMapping[$manzanaLetter] = $index;
                
                // Obtener el valor de financiamiento de la fila 2
                $financingValue = isset($financingValuesRow[$index]) ? trim($financingValuesRow[$index]) : '';
                
                // Determinar tipo de financiamiento basado en el valor
                if (strtoupper($financingValue) === 'CONTADO' || strtoupper($financingValue) === 'CASH') {
                    // Es pago al contado
                    $financingRules[$manzanaLetter] = [
                        'type' => 'cash_only',
                        'installments' => null,
                        'column_index' => $index
                    ];
                } elseif (is_numeric($financingValue) && (int)$financingValue > 0) {
                    // Es financiamiento en cuotas
                    $installments = (int)$financingValue;
                    $financingRules[$manzanaLetter] = [
                        'type' => 'installments',
                        'installments' => $installments,
                        'column_index' => $index
                    ];
                } else {
                    // Valor no válido, saltar esta manzana
                    unset($columnMapping[$manzanaLetter]);
                }
            }
        }
        
        return [
            'rules' => $financingRules,
            'column_mapping' => $columnMapping
        ];
    }

    /**
     * Actualiza las reglas de financiamiento en la base de datos
     */
    protected function updateManzanaFinancingRules(array $financingRules): void
    {
        foreach ($financingRules as $manzanaLetter => $rule) {
            // Crear manzana automáticamente si no existe
            $manzana = Manzana::where('name', $manzanaLetter)->first();
            if (!$manzana) {
                $manzana = Manzana::create([
                    'name' => $manzanaLetter
                ]);
            }
            
            ManzanaFinancingRule::updateOrCreate(
                ['manzana_id' => $manzana->manzana_id],
                [
                    'financing_type' => $rule['type'],
                    'max_installments' => $rule['installments'],
                    'allows_balloon_payment' => $rule['type'] === 'installments',
                    'allows_bpp_bonus' => true
                ]
            );
        }
    }

    /**
     * Procesa una fila individual del Excel
     */
    protected function processRow(array $row, array $headers, array $financingRules, array $columnMapping): void
    {
        // Mapear datos de la fila
        $data = array_combine($headers, $row);
        
        // Limpiar formato de precios (remover espacios y comas)
        $data = $this->cleanPriceData($data);
        
        // Validar que la manzana tenga reglas de financiamiento definidas en el Excel
        $manzanaLetter = $data['MZNA'];
        if (!isset($financingRules[$manzanaLetter])) {
            throw new Exception("Manzana {$manzanaLetter} no tiene reglas de financiamiento definidas en el Excel");
        }
        
        $manzanaRule = $financingRules[$manzanaLetter];
        
        // Extraer el monto específico de financiamiento para esta manzana
        $specificAmount = null;
        if (isset($columnMapping[$manzanaLetter])) {
            $columnIndex = $columnMapping[$manzanaLetter];
            $specificAmount = isset($row[$columnIndex]) ? $this->cleanNumericValue($row[$columnIndex]) : 0;
        }
        
        // Crear o actualizar el lote
        $lot = $this->createOrUpdateLot($data);
        
        // Crear template financiero con el monto específico
        $this->createFinancialTemplate($lot, $data, $manzanaRule, $specificAmount);
    }
    
    /**
     * Crea o actualiza un lote
     */
    protected function createOrUpdateLot(array $data): Lot
    {
        // Crear manzana automáticamente si no existe
        $manzana = Manzana::where('name', $data['MZNA'])->first();
        if (!$manzana) {
            $manzana = Manzana::create([
                'name' => $data['MZNA']
            ]);
        }
        
        // Crear tipo de calle automáticamente si no existe
        $streetType = StreetType::where('name', $data['UBICACIÓN'])->first();
        if (!$streetType) {
            $streetType = StreetType::create([
                'name' => $data['UBICACIÓN'],
                'description' => 'Creado automáticamente durante importación'
            ]);
        }
        
        return Lot::updateOrCreate(
            [
                'manzana_id' => $manzana->manzana_id,
                'num_lot' => $data['LOTE']
            ],
            [
                'street_type_id' => $streetType->street_type_id,
                'area_m2' => $this->cleanNumericValue($data['ÁREA LOTE']),
                'total_price' => $this->cleanNumericValue($data['PRECIO LISTA']),
                'currency' => 'PEN',
                'status' => 'disponible'
            ]
        );
    }
    
    /**
     * Crea el template financiero para el lote
     */
    protected function createFinancialTemplate(Lot $lot, array $data, array $manzanaRule, ?float $specificAmount): void
    {
        $templateData = [
            'lot_id' => $lot->lot_id,
            'precio_lista' => $this->cleanNumericValue($data['PRECIO LISTA']),
            'descuento' => $this->cleanNumericValue($data['DSCTO'] ?? 0),
            'precio_venta' => $this->cleanNumericValue($data['PRECIO VENTA']),
            'cuota_balon' => $this->cleanNumericValue($data['CUOTA BALON'] ?? 0),
            'bono_bpp' => $this->cleanNumericValue($data['BONO BPP'] ?? 0),
            'cuota_inicial' => $this->cleanNumericValue($data['CUOTA INICIAL'] ?? 0),
            'ci_fraccionamiento' => $this->cleanNumericValue($data['CI FRACC'] ?? 0)
        ];
        
        // Agregar el monto específico según el tipo de financiamiento de la manzana
        if ($manzanaRule['type'] === 'cash_only') {
            // Para manzana A: usar el monto específico de la columna CONTADO
            $templateData['precio_contado'] = $specificAmount > 0 ? $specificAmount : $this->cleanNumericValue($data['PRECIO VENTA'] ?? 0);
        } elseif ($manzanaRule['type'] === 'installments') {
            // Para manzanas con financiamiento: usar el monto específico de cuota
            $installments = $manzanaRule['installments'];
            $cleanSpecificAmount = $this->cleanNumericValue($specificAmount);
            $templateData["installments_{$installments}"] = $cleanSpecificAmount;
            
            // Validar que el monto específico sea mayor a 0 (permitir 0 para casos sin financiamiento específico)
            if ($cleanSpecificAmount < 0) {
                throw new Exception("Monto de cuota inválido para manzana {$data['MZNA']}: {$cleanSpecificAmount}");
            }
        }
        
        LotFinancialTemplate::updateOrCreate(
            ['lot_id' => $lot->lot_id],
            $templateData
        );
    }

    /**
     * Valida que los headers requeridos estén presentes
     */
    protected function validateHeaders(array $headers): void
    {
        $missing = array_diff($this->requiredHeaders, $headers);
        
        if (!empty($missing)) {
            throw new Exception('Faltan columnas requeridas: ' . implode(', ', $missing));
        }
    }

    /**
     * Obtiene estadísticas de importación
     */
    public function getImportStatistics(): array
    {
        return [
            'total_lots' => Lot::count(),
            'lots_with_financial_templates' => LotFinancialTemplate::count(),
            'manzanas_with_financing_rules' => ManzanaFinancingRule::count(),
            'available_lots' => Lot::where('status', 'available')->count(),
            'cash_only_manzanas' => ManzanaFinancingRule::cashOnly()->count(),
            'installment_manzanas' => ManzanaFinancingRule::allowingInstallments()->count()
        ];
    }

    /**
     * Valida la estructura del archivo Excel antes de procesarlo
     */
    public function validateExcelStructure(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = $worksheet->rangeToArray('A1:Z1')[0];
            
            $validation = [
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'detected_manzanas' => []
            ];
            
            // Validar headers requeridos
            $missing = array_diff($this->requiredHeaders, $headers);
            if (!empty($missing)) {
                $validation['valid'] = false;
                $validation['errors'][] = 'Faltan columnas requeridas: ' . implode(', ', $missing);
            }
            
            // Detectar manzanas dinámicamente (cualquier letra A-Z)
            foreach ($headers as $header) {
                if (preg_match('/^[A-Z]$/', $header)) {
                    $validation['detected_manzanas'][] = $header;
                }
            }
            
            if (empty($validation['detected_manzanas'])) {
                $validation['warnings'][] = 'No se detectaron columnas de manzanas específicas';
            }
            
            return $validation;
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Error al leer el archivo: ' . $e->getMessage()],
                'warnings' => [],
                'detected_manzanas' => []
            ];
        }
    }

    /**
     * Limpia los datos de precio removiendo espacios y comas
     */
    protected function cleanPriceData(array $data): array
    {
        $priceFields = [
            'PRECIO LISTA', 'PRECIO VENTA', 'CUOTA INICIAL', 'CUOTA BALON',
            'BONO BPP', 'DSCTO', 'CI FRACC', 'ÁREA LOTE'
        ];
        
        foreach ($priceFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->cleanNumericValue($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Limpia un valor numérico manejando correctamente separadores de miles (comas)
     */
    protected function cleanNumericValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        
        // Convertir a string si no lo es
        $cleanValue = (string) $value;
        
        // Remover espacios en blanco
        $cleanValue = trim($cleanValue);
        
        // Detectar si tiene formato con comas como separadores de miles
        // Formato esperado: 1,234.56 o 1,234,567.89
        if (preg_match('/^-?\d{1,3}(,\d{3})*(\.\d+)?$/', $cleanValue)) {
            // Es un número con comas como separadores de miles
            // Remover las comas para obtener el número limpio
            $cleanValue = str_replace(',', '', $cleanValue);
        } else {
            // Para otros formatos, remover caracteres no numéricos excepto punto y signo negativo
            $cleanValue = preg_replace('/[^\d.-]/', '', $cleanValue);
        }
        
        // Convertir a float
        return (float) $cleanValue;
    }

}