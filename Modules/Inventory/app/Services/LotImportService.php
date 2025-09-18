<?php

namespace Modules\Inventory\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\LotImportLog;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use App\Models\AsyncImportProcess;
use Exception;

class LotImportService
{
    protected array $requiredHeaders = [
        'MZNA', 'LOTE', 'ÁREA LOTE', 'UBICACIÓN', 'PRECIO m2', 
        'PRECIO LISTA', 'DSCTO', 'PRECIO VENTA', 'CUOTA BALON', 
        'BONO BPP', 'CUOTA INICIAL', 'CI FRACC', 'ESTADO'
    ];

    /**
     * Estados válidos para los lotes
     */
    protected array $validStatuses = [
        'disponible', 'reservado', 'vendido', 'cancelado', 'no_disponible'
    ];

    /**
     * Procesa el archivo Excel y realiza la importación de lotes
     */
    public function processExcel(UploadedFile $file): array
    {
        // Crear registro de importación
        $importLog = LotImportLog::create([
            'user_id' => Auth::id(),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_path' => $file->getPathname(),
            'status' => LotImportLog::STATUS_PROCESSING,
            'started_at' => now()
        ]);

        try {
            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                $importLog->markAsFailed('Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.');
                throw new Exception('Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.');
            }
            
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

            // Actualizar total de filas en el log
            $importLog->update([
                'total_rows' => count($rows),
                'processed_rows' => 0
            ]);

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
                    
                    // Actualizar progreso
                    $importLog->update([
                        'processed_rows' => $index + 1
                    ]);
                }
                
                DB::commit();
                
                // Marcar como completado
                $importLog->markAsCompleted([
                    'success' => $results['success'],
                    'errors' => $results['errors']
                ]);
                
            } catch (Exception $e) {
                DB::rollback();
                $importLog->markAsFailed('Error durante el procesamiento: ' . $e->getMessage(), $results['errors']);
                throw $e;
            }

            return $results;
            
        } catch (Exception $e) {
            $importLog->markAsFailed('Error al procesar archivo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extrae las reglas de financiamiento dinámicamente desde los headers y fila 2 del Excel
     * Detecta automáticamente cualquier columna de manzana (A-Z) y lee sus valores de financiamiento
     */
    protected function extractFinancingRules(array $headerRow, array $financingValuesRow): array
    {
        $financingRules = [];
        $columnMapping = [];
        
        // Log inicial para diagnóstico
        \Log::info("[LotImport] Iniciando extractFinancingRules", [
            'total_headers' => count($headerRow),
            'total_financing_values' => count($financingValuesRow),
            'headers_sample' => array_slice($headerRow, 0, 20),
            'financing_values_sample' => array_slice($financingValuesRow, 0, 20)
        ]);
        
        // Detectar automáticamente columnas de manzanas (letras A-Z)
        foreach ($headerRow as $index => $header) {
            // Limpiar el header de espacios y caracteres especiales
            $cleanHeader = trim($header);
            $originalHeader = $header;
            
            // Log detallado de cada header
            \Log::debug("[LotImport] Procesando header", [
                'index' => $index,
                'original_header' => $originalHeader,
                'clean_header' => $cleanHeader,
                'header_length' => strlen($cleanHeader),
                'is_empty' => empty($cleanHeader),
                'char_codes' => array_map('ord', str_split($cleanHeader))
            ]);
            
            // Validaciones adicionales para headers problemáticos
            if (empty($cleanHeader)) {
                \Log::debug("[LotImport] Header vacío encontrado", [
                    'index' => $index,
                    'original_header' => $originalHeader,
                    'original_length' => strlen($originalHeader)
                ]);
                continue;
            }
            
            // Detectar headers con solo espacios en blanco
            if (ctype_space($originalHeader)) {
                \Log::debug("[LotImport] Header con solo espacios encontrado", [
                    'index' => $index,
                    'original_header' => $originalHeader,
                    'original_length' => strlen($originalHeader)
                ]);
                continue;
            }
            
            // Detectar headers con caracteres especiales o no ASCII
            if (!ctype_print($cleanHeader) || preg_match('/[^A-Za-z0-9]/', $cleanHeader)) {
                \Log::debug("[LotImport] Header con caracteres especiales", [
                    'index' => $index,
                    'clean_header' => $cleanHeader,
                    'has_special_chars' => preg_match('/[^A-Za-z0-9]/', $cleanHeader) ? 'yes' : 'no',
                    'is_printable' => ctype_print($cleanHeader) ? 'yes' : 'no'
                ]);
            }
            
            // Verificar si el header es una sola letra (manzana)
            if (preg_match('/^[A-Z]$/', $cleanHeader)) {
                $manzanaLetter = $cleanHeader;
                $columnMapping[$manzanaLetter] = $index;
                
                // Obtener el valor de financiamiento de la fila 2
                $financingValue = isset($financingValuesRow[$index]) ? trim($financingValuesRow[$index]) : '';
                
                // Logging específico para manzana J
                if ($manzanaLetter === 'J') {
                    \Log::critical("[LotImport] MANZANA J DETECTADA - ANÁLISIS DETALLADO", [
                        'manzana' => $manzanaLetter,
                        'column_index' => $index,
                        'original_header' => $originalHeader,
                        'clean_header' => $cleanHeader,
                        'financing_value' => $financingValue,
                        'financing_value_raw' => $financingValuesRow[$index] ?? 'NOT_SET',
                        'financing_value_length' => strlen($financingValue),
                        'financing_value_empty' => empty($financingValue),
                        'is_numeric' => is_numeric($financingValue),
                        'upper_value' => strtoupper($financingValue),
                        'lower_value' => strtolower($financingValue),
                        'financing_char_codes' => array_map('ord', str_split($financingValue)),
                        'will_be_processed' => 'CHECKING_NEXT'
                    ]);
                }
                
                \Log::info("[LotImport] Manzana detectada", [
                    'manzana' => $manzanaLetter,
                    'column_index' => $index,
                    'financing_value' => $financingValue,
                    'financing_value_length' => strlen($financingValue),
                    'is_numeric' => is_numeric($financingValue),
                    'upper_value' => strtoupper($financingValue)
                ]);
                
                // Determinar tipo de financiamiento basado en el valor
                if (strtoupper($financingValue) === 'CONTADO' || strtoupper($financingValue) === 'CASH') {
                    // Es pago al contado
                    $financingRules[$manzanaLetter] = [
                        'type' => 'cash_only',
                        'installments' => null,
                        'column_index' => $index
                    ];
                    \Log::info("[LotImport] Configurada manzana CONTADO", ['manzana' => $manzanaLetter]);
                } elseif (is_numeric($financingValue) && (int)$financingValue > 0) {
                    // Es financiamiento en cuotas
                    $installments = (int)$financingValue;
                    $financingRules[$manzanaLetter] = [
                        'type' => 'installments',
                        'installments' => $installments,
                        'column_index' => $index
                    ];
                    \Log::info("[LotImport] Configurada manzana con CUOTAS", [
                        'manzana' => $manzanaLetter,
                        'installments' => $installments
                    ]);
                } else {
                    // Logging específico para manzana J cuando se salta
                    if ($manzanaLetter === 'J') {
                        \Log::critical("[LotImport] MANZANA J SALTADA - ANÁLISIS DEL PROBLEMA", [
                            'manzana' => $manzanaLetter,
                            'financing_value' => $financingValue,
                            'financing_value_raw' => $financingValuesRow[$index] ?? 'NOT_SET',
                            'is_contado_check' => strtoupper($financingValue) === 'CONTADO',
                            'is_cash_check' => strtoupper($financingValue) === 'CASH',
                            'is_numeric_check' => is_numeric($financingValue),
                            'numeric_value' => is_numeric($financingValue) ? (int)$financingValue : 'NOT_NUMERIC',
                            'is_greater_than_zero' => is_numeric($financingValue) && (int)$financingValue > 0,
                            'reason' => 'Valor no es CONTADO ni numérico válido',
                            'possible_values_detected' => [
                                'empty' => empty($financingValue),
                                'no_disponible' => strtolower($financingValue) === 'no disponible',
                                'na' => strtolower($financingValue) === 'n/a',
                                'contains_text' => !is_numeric($financingValue) && !empty($financingValue)
                            ]
                        ]);
                    }
                    
                    // Valor no válido, saltar esta manzana
                    \Log::warning("[LotImport] Manzana con valor inválido - SALTADA", [
                        'manzana' => $manzanaLetter,
                        'financing_value' => $financingValue,
                        'reason' => 'Valor no es CONTADO ni numérico válido'
                    ]);
                    unset($columnMapping[$manzanaLetter]);
                }
            } elseif (!empty($cleanHeader)) {
                // Log de headers que no son manzanas pero no están vacíos
                \Log::debug("[LotImport] Header no es manzana", [
                    'header' => $cleanHeader,
                    'matches_pattern' => preg_match('/^[A-Z]$/', $cleanHeader) ? 'yes' : 'no',
                    'reason' => 'No coincide con patrón de una sola letra A-Z'
                ]);
            }
        }
        
        // Log final del resultado
        \Log::info("[LotImport] Resultado extractFinancingRules", [
            'total_manzanas_detected' => count($financingRules),
            'manzanas_detected' => array_keys($financingRules),
            'column_mapping' => $columnMapping,
            'financing_rules_summary' => array_map(function($rule) {
                return [
                    'type' => $rule['type'],
                    'installments' => $rule['installments'] ?? null
                ];
            }, $financingRules)
        ]);
        
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
        
        \Log::debug("[LotImport] Procesando fila", [
            'row_data_sample' => array_slice($row, 0, 10),
            'extracted_manzana' => $data['MZNA'] ?? 'NO_EXTRAIDA',
            'extracted_lote' => $data['LOTE'] ?? 'NO_EXTRAIDO'
        ]);
        
        // Limpiar formato de precios (remover espacios y comas)
        $data = $this->cleanPriceData($data);
        
        // Validar que la manzana tenga reglas de financiamiento definidas en el Excel
        $manzanaLetter = $data['MZNA'];
        
        \Log::info("[LotImport] Buscando reglas para manzana", [
            'manzana_original' => $manzanaLetter,
            'available_manzanas' => array_keys($financingRules),
            'total_available_rules' => count($financingRules),
            'manzana_exists_in_rules' => isset($financingRules[$manzanaLetter]) ? 'YES' : 'NO'
        ]);
        
        if (!isset($financingRules[$manzanaLetter])) {
            \Log::error("[LotImport] Manzana sin reglas de financiamiento", [
                'manzana_buscada' => $manzanaLetter,
                'manzanas_disponibles' => array_keys($financingRules),
                'comparacion_exacta' => array_map(function($availableManzana) use ($manzanaLetter) {
                    return [
                        'available' => $availableManzana,
                        'searching' => $manzanaLetter,
                        'match' => $availableManzana === $manzanaLetter ? 'YES' : 'NO',
                        'available_length' => strlen($availableManzana),
                        'searching_length' => strlen($manzanaLetter),
                        'available_chars' => array_map('ord', str_split($availableManzana)),
                        'searching_chars' => array_map('ord', str_split($manzanaLetter))
                    ];
                }, array_keys($financingRules))
            ]);
            
            throw new Exception("Manzana {$manzanaLetter} no tiene reglas de financiamiento definidas en el Excel");
        }
        
        $manzanaRule = $financingRules[$manzanaLetter];
        
        \Log::info("[LotImport] Regla de financiamiento encontrada", [
            'manzana' => $manzanaLetter,
            'financing_rule' => $manzanaRule
        ]);
        
        // Extraer el monto específico de financiamiento para esta manzana
        $specificAmount = null;
        if (isset($columnMapping[$manzanaLetter])) {
            $columnIndex = $columnMapping[$manzanaLetter];
            $specificAmount = isset($row[$columnIndex]) ? $this->cleanNumericValue($row[$columnIndex]) : 0;
        }
        
        // Crear o actualizar el lote
        $lot = $this->createOrUpdateLot($data);
        
        // Crear template financiero con el monto específico y validación adicional
        try {
            \Log::info("[LotImport] Procesando template financiero en processRow", [
                'lot_id' => $lot->lot_id,
                'manzana' => $data['MZNA'] ?? 'N/A',
                'lote' => $data['LOTE'] ?? 'N/A'
            ]);
            
            $this->createFinancialTemplate($lot, $data, $manzanaRule, $specificAmount);
            
            // Verificar que el template se creó correctamente
            $templateExists = LotFinancialTemplate::where('lot_id', $lot->lot_id)->exists();
            if (!$templateExists) {
                \Log::error("[LotImport] Template financiero no se encontró después de creación", [
                    'lot_id' => $lot->lot_id
                ]);
                throw new Exception("Template financiero no se creó para lote {$lot->lot_id}");
            }
            
            \Log::info("[LotImport] Template financiero verificado exitosamente", [
                'lot_id' => $lot->lot_id
            ]);
            
        } catch (Exception $e) {
            \Log::error("[LotImport] Error al procesar template financiero en processRow", [
                'lot_id' => $lot->lot_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
        
        // Validar y limpiar el estado del lote
        $status = $this->validateAndCleanStatus($data['ESTADO'] ?? 'disponible');
        
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
                'status' => $status
            ]
        );
    }
    
    /**
     * Crea el template financiero para el lote
     */
    protected function createFinancialTemplate(Lot $lot, array $data, array $manzanaRule, ?float $specificAmount): void
    {
        try {
            // Log inicial del proceso
            \Log::info("[LotImport] Iniciando creación de template financiero", [
                'lot_id' => $lot->lot_id,
                'manzana' => $data['MZNA'] ?? 'N/A',
                'lote' => $data['LOTE'] ?? 'N/A',
                'manzana_rule_type' => $manzanaRule['type'] ?? 'N/A',
                'specific_amount' => $specificAmount
            ]);
            
            // Verificar que el lote existe en la base de datos
            $lotExists = Lot::where('lot_id', $lot->lot_id)->exists();
            if (!$lotExists) {
                throw new Exception("El lote con ID {$lot->lot_id} no existe en la base de datos");
            }
            
            \Log::info("[LotImport] Lote verificado en BD", ['lot_id' => $lot->lot_id]);
            
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
                \Log::info("[LotImport] Configurando manzana cash_only", [
                    'precio_contado' => $templateData['precio_contado']
                ]);
            } elseif ($manzanaRule['type'] === 'installments') {
                // Para manzanas con financiamiento: usar el monto específico de cuota
                $installments = $manzanaRule['installments'];
                $cleanSpecificAmount = $this->cleanNumericValue($specificAmount);
                $templateData["installments_{$installments}"] = $cleanSpecificAmount;
                
                \Log::info("[LotImport] Configurando manzana con cuotas", [
                    'installments' => $installments,
                    'amount' => $cleanSpecificAmount
                ]);
                
                // Validar que el monto específico sea mayor a 0 (permitir 0 para casos sin financiamiento específico)
                if ($cleanSpecificAmount < 0) {
                    throw new Exception("Monto de cuota inválido para manzana {$data['MZNA']}: {$cleanSpecificAmount}");
                }
            }
            
            // Log de los datos que se van a insertar
            \Log::info("[LotImport] Datos del template a crear/actualizar", [
                'template_data' => $templateData
            ]);
            
            // Intentar crear/actualizar el template financiero
            $template = LotFinancialTemplate::updateOrCreate(
                ['lot_id' => $lot->lot_id],
                $templateData
            );
            
            // Verificar que se creó correctamente
            if ($template && $template->exists) {
                \Log::info("[LotImport] Template financiero creado/actualizado exitosamente", [
                    'template_id' => $template->getKey(),
                    'lot_id' => $template->lot_id,
                    'was_recently_created' => $template->wasRecentlyCreated
                ]);
            } else {
                \Log::error("[LotImport] Error: Template financiero no se pudo crear", [
                    'lot_id' => $lot->lot_id,
                    'template_result' => $template
                ]);
                throw new Exception("No se pudo crear el template financiero para el lote {$lot->lot_id}");
            }
            
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error("[LotImport] Error de base de datos al crear template financiero", [
                'lot_id' => $lot->lot_id,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'error_info' => $e->errorInfo ?? null
            ]);
            throw new Exception("Error de base de datos al crear template financiero para lote {$lot->lot_id}: {$e->getMessage()}");
        } catch (Exception $e) {
            \Log::error("[LotImport] Error general al crear template financiero", [
                'lot_id' => $lot->lot_id,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
     * Método de diagnóstico para verificar lotes sin template financiero
     */
    public function diagnoseLotFinancialTemplates(): array
    {
        try {
            \Log::info("[LotImport] Iniciando diagnóstico de templates financieros");
            
            // Obtener todos los lotes
            $totalLots = Lot::count();
            
            // Obtener lotes sin template financiero
            $lotsWithoutTemplate = Lot::leftJoin('lot_financial_templates', 'lots.lot_id', '=', 'lot_financial_templates.lot_id')
                ->whereNull('lot_financial_templates.lot_id')
                ->select('lots.lot_id', 'lots.num_lot', 'lots.manzana_id')
                ->with('manzana')
                ->get();
            
            // Obtener lotes con template financiero
            $lotsWithTemplate = Lot::join('lot_financial_templates', 'lots.lot_id', '=', 'lot_financial_templates.lot_id')
                ->count();
            
            // Verificar integridad de foreign keys
            $orphanedTemplates = \DB::table('lot_financial_templates')
                ->leftJoin('lots', 'lot_financial_templates.lot_id', '=', 'lots.lot_id')
                ->whereNull('lots.lot_id')
                ->count();
            
            // Verificar estructura de tabla
            $tableExists = \Schema::hasTable('lot_financial_templates');
            $tableColumns = $tableExists ? \Schema::getColumnListing('lot_financial_templates') : [];
            
            $diagnosticResult = [
                'database_status' => [
                    'table_exists' => $tableExists,
                    'table_columns' => $tableColumns,
                    'expected_columns' => [
                        'lot_id', 'precio_lista', 'descuento', 'precio_venta', 
                        'precio_contado', 'cuota_balon', 'bono_bpp', 'cuota_inicial', 
                        'ci_fraccionamiento', 'installments_12', 'installments_24', 
                        'installments_36', 'installments_48', 'installments_60'
                    ]
                ],
                'lot_statistics' => [
                    'total_lots' => $totalLots,
                    'lots_with_template' => $lotsWithTemplate,
                    'lots_without_template' => $lotsWithoutTemplate->count(),
                    'orphaned_templates' => $orphanedTemplates
                ],
                'lots_without_template_details' => $lotsWithoutTemplate->map(function($lot) {
                    return [
                        'lot_id' => $lot->lot_id,
                        'num_lot' => $lot->num_lot,
                        'manzana' => $lot->manzana ? $lot->manzana->name : 'N/A'
                    ];
                })->toArray()
            ];
            
            \Log::info("[LotImport] Diagnóstico completado", $diagnosticResult);
            
            return $diagnosticResult;
            
        } catch (Exception $e) {
            \Log::error("[LotImport] Error en diagnóstico", [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida la estructura del archivo Excel antes de procesarlo
     */
    public function validateExcelStructure(UploadedFile $file): array
    {
        try {
            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                return [
                    'valid' => false,
                    'errors' => ['Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.'],
                    'warnings' => [],
                    'detected_manzanas' => []
                ];
            }
            
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
     * Valida y limpia el estado del lote
     */
    protected function validateAndCleanStatus(string $status): string
    {
        // Limpiar el estado (remover espacios y convertir a minúsculas)
        $cleanStatus = strtolower(trim($status));
        
        // Mapear posibles variaciones de estado
        $statusMapping = [
            'disponible' => 'disponible',
            'available' => 'disponible',
            'libre' => 'disponible',
            'ocupado' => 'vendido',
            'reservado' => 'reservado',
            'reserved' => 'reservado',
            'vendido' => 'vendido',
            'sold' => 'vendido',
            'cancelado' => 'cancelado',
            'cancelled' => 'cancelado',
            'canceled' => 'cancelado',
            'no disponible' => 'no_disponible',
            'not available' => 'no_disponible',
            'no_disponible' => 'no_disponible'
        ];
        
        // Buscar el estado en el mapeo
        if (isset($statusMapping[$cleanStatus])) {
            $mappedStatus = $statusMapping[$cleanStatus];
            
            // Verificar que el estado mapeado esté en la lista de estados válidos
            if (in_array($mappedStatus, $this->validStatuses)) {
                return $mappedStatus;
            }
        }
        
        // Si no se encuentra un estado válido, lanzar excepción
        throw new Exception("Estado de lote inválido: '{$status}'. Estados válidos: " . implode(', ', $this->validStatuses));
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

    /**
     * Procesa el archivo Excel de forma asíncrona
     */
    public function processExcelAsync(AsyncImportProcess $importProcess, array $options = []): void
    {
        try {
            // Marcar como iniciado
            $importProcess->updateProgress(0, 'Iniciando procesamiento...');
            $importProcess->update(['started_at' => now()]);
            
            // Cargar el archivo desde storage
            $filePath = storage_path('app/' . $importProcess->file_path);
            
            if (!file_exists($filePath)) {
                throw new Exception('Archivo no encontrado: ' . $filePath);
            }
            
            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                throw new Exception('Error del servidor: La extensión ZIP de PHP no está disponible. Contacte al administrador del sistema.');
            }
            
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Extraer headers y fila de valores de financiamiento
            $headers = $rows[0];
            $financingValuesRow = isset($rows[1]) ? $rows[1] : [];
            
            // Extraer reglas de financiamiento y mapeo de columnas
            $financingData = $this->extractFinancingRules($headers, $financingValuesRow);
            $financingRules = $financingData['rules'];
            $columnMapping = $financingData['column_mapping'];
            
            // Validar headers
            $this->validateHeaders($headers);
            
            // Remover las primeras dos filas
            array_shift($rows); // headers
            array_shift($rows); // valores de financiamiento

            $totalRows = count($rows);
            $successCount = 0;
            $errors = [];
            $warnings = [];
            
            // Actualizar total de filas
            $importProcess->update([
                'total_rows' => $totalRows,
                'processed_rows' => 0
            ]);
            
            $importProcess->updateProgress(5, 'Validando estructura del archivo...');

            DB::beginTransaction();
            try {
                // Crear/actualizar reglas de financiamiento por manzana
                $this->updateManzanaFinancingRules($financingRules);
                
                $importProcess->updateProgress(10, 'Procesando lotes...');
                
                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 3; // +3 porque removimos headers y fila de financiamiento
                    
                    try {
                        // Solo validar si está habilitado
                        if ($options['validate_only'] ?? false) {
                            $this->validateRowData($row, $headers, $financingRules);
                        } else {
                            $this->processRow($row, $headers, $financingRules, $columnMapping);
                        }
                        
                        $successCount++;
                    } catch (Exception $e) {
                        $errorMsg = "Fila {$rowNumber}: {$e->getMessage()}";
                        $errors[] = $errorMsg;
                        
                        // Si no se permite saltar duplicados, fallar completamente
                        if (!($options['skip_duplicates'] ?? true) && str_contains($e->getMessage(), 'duplicate')) {
                            throw new Exception($errorMsg);
                        }
                    }
                    
                    // Actualizar progreso cada 10 filas o en la última
                    if (($index + 1) % 10 === 0 || $index === $totalRows - 1) {
                        $processedRows = $index + 1;
                        $progressPercentage = (int)(($processedRows / $totalRows) * 85) + 10; // 10-95%
                        
                        $importProcess->update([
                            'processed_rows' => $processedRows,
                            'successful_rows' => $successCount,
                            'failed_rows' => count($errors),
                            'progress_percentage' => $progressPercentage,
                            'errors' => $errors,
                            'warnings' => $warnings
                        ]);
                        
                        $importProcess->updateProgress(
                            $progressPercentage, 
                            "Procesando fila {$processedRows} de {$totalRows}..."
                        );
                    }
                }
                
                DB::commit();
                
                // Completar el proceso
                $summary = [
                    'total_rows' => $totalRows,
                    'successful_rows' => $successCount,
                    'failed_rows' => count($errors),
                    'financing_rules_created' => count($financingRules),
                    'validation_only' => $options['validate_only'] ?? false
                ];
                
                $importProcess->markAsCompleted($summary);
                
            } catch (Exception $e) {
                DB::rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $importProcess->markAsFailed($e->getMessage(), $errors ?? []);
            throw $e;
        }
    }
    
    /**
     * Valida los datos de una fila sin procesarla
     */
    protected function validateRowData(array $row, array $headers, array $financingRules): void
    {
        // Mapear datos de la fila
        $data = array_combine($headers, $row);
        
        // Validar campos requeridos
        $requiredFields = ['MZNA', 'LOTE', 'ÁREA LOTE', 'PRECIO LISTA'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo requerido vacío: {$field}");
            }
        }
        
        // Validar que la manzana tenga reglas de financiamiento
        $manzanaLetter = $data['MZNA'];
        if (!isset($financingRules[$manzanaLetter])) {
            throw new Exception("Manzana {$manzanaLetter} no tiene reglas de financiamiento definidas");
        }
        
        // Validar valores numéricos
        $numericFields = ['ÁREA LOTE', 'PRECIO LISTA', 'PRECIO VENTA'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($this->cleanNumericValue($data[$field]))) {
                throw new Exception("Valor numérico inválido en campo {$field}: {$data[$field]}");
            }
        }
        
        // Validar estado si está presente
        if (!empty($data['ESTADO'])) {
            $this->validateAndCleanStatus($data['ESTADO']);
        }
    }
    
    /**
     * Registra errores durante la importación
     */
    protected function logError(string $message, array $rowData = []): void
    {
        $this->errors[] = $message;
        \Log::error("[LotImport] {$message}", [
            'row_data' => $rowData,
            'timestamp' => now()
        ]);
    }
    
    /**
     * Método de diagnóstico específico para analizar la columna J
     */
    public function diagnoseColumnJ(array $headerRow, array $financingValuesRow): array
    {
        $diagnosis = [
            'column_j_analysis' => [],
            'found_j_columns' => [],
            'potential_issues' => [],
            'recommendations' => []
        ];
        
        // Buscar todas las posibles columnas J
        foreach ($headerRow as $index => $header) {
            $originalHeader = $header;
            $cleanHeader = trim($header);
            $financingValue = isset($financingValuesRow[$index]) ? trim($financingValuesRow[$index]) : '';
            
            // Analizar si podría ser la columna J
            $couldBeJ = false;
            $issues = [];
            
            // Verificar si el header contiene 'J'
            if (stripos($cleanHeader, 'J') !== false) {
                $couldBeJ = true;
                
                $columnAnalysis = [
                    'column_index' => $index,
                    'original_header' => $originalHeader,
                    'clean_header' => $cleanHeader,
                    'header_length' => strlen($cleanHeader),
                    'financing_value' => $financingValue,
                    'financing_value_length' => strlen($financingValue),
                    'char_codes' => array_map('ord', str_split($cleanHeader)),
                    'is_exact_j' => $cleanHeader === 'J',
                    'matches_pattern' => preg_match('/^[A-Z]$/', $cleanHeader) ? true : false,
                    'is_valid_financing' => $this->isValidFinancingValue($financingValue)
                ];
                
                // Identificar problemas específicos
                if ($cleanHeader !== 'J') {
                    $issues[] = "Header no es exactamente 'J': '{$cleanHeader}'";
                }
                
                if (!preg_match('/^[A-Z]$/', $cleanHeader)) {
                    $issues[] = "Header no coincide con patrón de una sola letra A-Z";
                }
                
                if (empty($financingValue)) {
                    $issues[] = "Valor de financiamiento está vacío";
                }
                
                if (!empty($financingValue) && !$this->isValidFinancingValue($financingValue)) {
                    $issues[] = "Valor de financiamiento inválido: '{$financingValue}'";
                }
                
                if (strtolower($financingValue) === 'no disponible' || strtolower($financingValue) === 'n/a') {
                    $issues[] = "Valor indica 'no disponible' o 'N/A'";
                }
                
                $columnAnalysis['issues'] = $issues;
                $diagnosis['found_j_columns'][] = $columnAnalysis;
            }
        }
        
        // Generar recomendaciones
        if (empty($diagnosis['found_j_columns'])) {
            $diagnosis['recommendations'][] = "No se encontró ninguna columna que contenga 'J' en los headers";
            $diagnosis['recommendations'][] = "Verificar que el archivo Excel tenga una columna con header exactamente 'J'";
        } else {
            foreach ($diagnosis['found_j_columns'] as $column) {
                if (!empty($column['issues'])) {
                    $diagnosis['potential_issues'] = array_merge($diagnosis['potential_issues'], $column['issues']);
                }
                
                if ($column['is_exact_j'] && $column['matches_pattern'] && $column['is_valid_financing']) {
                    $diagnosis['recommendations'][] = "Columna J encontrada y válida en índice {$column['column_index']}";
                } elseif ($column['is_exact_j'] && $column['matches_pattern']) {
                    $diagnosis['recommendations'][] = "Columna J encontrada pero con valor de financiamiento inválido: '{$column['financing_value']}'";
                } elseif ($column['is_exact_j']) {
                    $diagnosis['recommendations'][] = "Header 'J' encontrado pero no coincide con patrón de manzana";
                }
            }
        }
        
        return $diagnosis;
    }
    
    /**
     * Método de diagnóstico para mostrar mapeo completo de headers vs valores de financiamiento
     */
    public function diagnoseFinancingRules(array $headerRow, array $financingValuesRow): array
    {
        $diagnosis = [
            'total_columns' => count($headerRow),
            'header_analysis' => [],
            'potential_manzanas' => [],
            'invalid_headers' => [],
            'empty_headers' => [],
            'financing_mapping' => []
        ];
        
        foreach ($headerRow as $index => $header) {
            $cleanHeader = trim($header);
            $financingValue = isset($financingValuesRow[$index]) ? trim($financingValuesRow[$index]) : '';
            
            $headerInfo = [
                'index' => $index,
                'original' => $header,
                'clean' => $cleanHeader,
                'length' => strlen($cleanHeader),
                'is_empty' => empty($cleanHeader),
                'financing_value' => $financingValue,
                'is_single_letter' => preg_match('/^[A-Z]$/', $cleanHeader),
                'char_codes' => array_map('ord', str_split($cleanHeader))
            ];
            
            $diagnosis['header_analysis'][] = $headerInfo;
            
            if (empty($cleanHeader)) {
                $diagnosis['empty_headers'][] = $headerInfo;
            } elseif (preg_match('/^[A-Z]$/', $cleanHeader)) {
                $diagnosis['potential_manzanas'][] = $headerInfo;
                $diagnosis['financing_mapping'][$cleanHeader] = [
                    'column_index' => $index,
                    'financing_value' => $financingValue,
                    'is_valid_financing' => $this->isValidFinancingValue($financingValue)
                ];
            } else {
                $diagnosis['invalid_headers'][] = $headerInfo;
            }
        }
        
        return $diagnosis;
    }
    
    /**
     * Verifica si un valor de financiamiento es válido
     */
    protected function isValidFinancingValue(string $value): bool
    {
        $upperValue = strtoupper(trim($value));
        return $upperValue === 'CONTADO' || 
               $upperValue === 'CASH' || 
               (is_numeric($value) && (int)$value > 0);
    }

}