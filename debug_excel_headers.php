<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class ExcelHeaderDebugger
{
    public function debugExcelHeaders($filePath)
    {
        echo "\n=== DEBUGGING EXCEL HEADERS ===\n";
        echo "File: {$filePath}\n";
        echo "File exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";
        
        if (!file_exists($filePath)) {
            echo "ERROR: File not found!\n";
            return;
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get the first row (headers)
            $headerRow = $worksheet->rangeToArray('A1:Z1', null, true, false)[0];
            
            echo "\n=== RAW HEADERS FOUND ===\n";
            $actualHeaders = [];
            
            foreach ($headerRow as $index => $header) {
                if (!empty($header)) {
                    $actualHeaders[] = $header;
                    $columnLetter = chr(65 + $index); // A, B, C, etc.
                    
                    echo "Column {$columnLetter} (Index {$index}): '{$header}'\n";
                    echo "  - Length: " . strlen($header) . " characters\n";
                    echo "  - Trimmed: '" . trim($header) . "'\n";
                    echo "  - Bytes: " . bin2hex($header) . "\n";
                    echo "  - ASCII codes: ";
                    for ($i = 0; $i < strlen($header); $i++) {
                        echo ord($header[$i]) . " ";
                    }
                    echo "\n";
                    
                    // Check for invisible characters
                    $cleanHeader = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header);
                    if ($cleanHeader !== $header) {
                        echo "  - WARNING: Contains invisible/special characters!\n";
                        echo "  - Clean version: '{$cleanHeader}'\n";
                    }
                    
                    echo "\n";
                }
            }
            
            // Expected headers for contract import
            $expectedHeaders = [
                'CLIENTE_NOMBRE_COMPLETO',
                'CLIENTE_DOCUMENTO',
                'CLIENTE_TELEFONO',
                'CLIENTE_EMAIL',
                'ASESOR_NOMBRE',
                'ASESOR_DOCUMENTO',
                'LOTE_CODIGO',
                'LOTE_MANZANA',
                'LOTE_NUMERO',
                'LOTE_AREA',
                'CONTRATO_PRECIO',
                'CONTRATO_FECHA',
                'CONTRATO_ESTADO',
                'OBSERVACIONES'
            ];
            
            echo "\n=== EXPECTED vs ACTUAL COMPARISON ===\n";
            echo "Expected headers count: " . count($expectedHeaders) . "\n";
            echo "Actual headers count: " . count($actualHeaders) . "\n\n";
            
            foreach ($expectedHeaders as $expected) {
                $found = false;
                $matchIndex = -1;
                
                foreach ($actualHeaders as $index => $actual) {
                    if (trim($actual) === $expected) {
                        $found = true;
                        $matchIndex = $index;
                        break;
                    }
                }
                
                echo "'{$expected}': " . ($found ? "FOUND at position {$matchIndex}" : "NOT FOUND") . "\n";
                
                if (!$found) {
                    // Look for similar headers
                    echo "  Similar headers found:\n";
                    foreach ($actualHeaders as $index => $actual) {
                        $similarity = similar_text(strtolower($expected), strtolower(trim($actual)), $percent);
                        if ($percent > 70) {
                            echo "    - '{$actual}' (similarity: {$percent}%)\n";
                        }
                    }
                }
            }
            
            echo "\n=== VALIDATION SIMULATION ===\n";
            $missingHeaders = [];
            foreach ($expectedHeaders as $expected) {
                if (!in_array($expected, array_map('trim', $actualHeaders))) {
                    $missingHeaders[] = $expected;
                }
            }
            
            if (empty($missingHeaders)) {
                echo "✅ ALL HEADERS FOUND - Import should work!\n";
            } else {
                echo "❌ MISSING HEADERS:\n";
                foreach ($missingHeaders as $missing) {
                    echo "  - {$missing}\n";
                }
            }
            
            echo "\n=== ORDER SENSITIVITY TEST ===\n";
            echo "Headers in file order:\n";
            foreach ($actualHeaders as $index => $header) {
                echo "  {$index}: {$header}\n";
            }
            
            echo "\nExpected order:\n";
            foreach ($expectedHeaders as $index => $header) {
                echo "  {$index}: {$header}\n";
            }
            
            // Check if order matters by looking at the validation code
            echo "\nNote: The validation typically checks for presence, not order.\n";
            echo "However, some import logic might depend on column positions.\n";
            
        } catch (Exception $e) {
            echo "ERROR reading Excel file: " . $e->getMessage() . "\n";
        }
    }
    
    public function debugAllExcelFiles()
    {
        // Files are in the project root directory
        $baseDir = dirname(__DIR__); // Go up one level from casaBonita_api
        $files = [
            'plantilla_test.xlsx',
            'template_test.xlsx', 
            'test_contracts_simplified.xlsx',
            'test_contracts_template_simplified.xlsx',
            'casaBonita_api/plantilla_importacion_contratos_simplificada.xlsx'
        ];
        
        foreach ($files as $file) {
            $fullPath = $baseDir . '/' . $file;
            if (file_exists($fullPath)) {
                echo "\n=== ANALYZING: {$file} ===\n";
                $this->debugExcelHeaders($fullPath);
                echo "\n" . str_repeat("=", 80) . "\n";
            } else {
                echo "File not found: {$fullPath}\n";
            }
        }
    }
}

// Run the debugger
$debugger = new ExcelHeaderDebugger();

echo "Starting Excel Header Debug Analysis...\n";
echo "Current directory: " . getcwd() . "\n";

// Debug all available Excel files
$debugger->debugAllExcelFiles();

echo "\n=== DEBUG COMPLETE ===\n";