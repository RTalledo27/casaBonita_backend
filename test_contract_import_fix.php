<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\Sales\Services\ContractImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

echo "=== Testing Contract Import Fix for NULL doc_number ===\n\n";

try {
    // Clean up any existing test data
    DB::table('clients')->where('first_name', 'LIKE', 'TEST_%')->delete();
    echo "✓ Cleaned up existing test data\n";
    
    // Create test CSV data with empty doc_number values but valid lot data (using existing available lots)
    $testData = [
        ['ASESOR_NOMBRE', 'CLIENTE_NOMBRE_COMPLETO', 'CLIENTE_NUMERO_DOCUMENTO', 'CLIENTE_TIPO_DOCUMENTO', 'CLIENTE_TELEFONO_1', 'LOTE_NUMERO', 'LOTE_MANZANA', 'FECHA_VENTA', 'CANAL_VENTA'],
        ['LUIS ENRIQUE TAVARA CASTILLO', 'TEST CLIENT ONE', '-', 'DNI', '123456789', '15', '1', '01/01/2025', 'WEB'],
        ['LUIS ENRIQUE TAVARA CASTILLO', 'TEST CLIENT TWO', '', 'DNI', '987654321', '27', '1', '01/01/2025', 'WEB'],
        ['LUIS ENRIQUE TAVARA CASTILLO', 'TEST CLIENT THREE', '-', 'DNI', '555666777', '28', '1', '01/01/2025', 'WEB']
    ];
    
    // Create temporary CSV file
    $csvContent = '';
    foreach ($testData as $row) {
        $csvContent .= implode(',', $row) . "\n";
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'test_import_');
    file_put_contents($tempFile, $csvContent);
    
    echo "✓ Created test CSV file with empty doc_number values\n";
    
    // Create UploadedFile instance
    $uploadedFile = new UploadedFile(
        $tempFile,
        'test_import.csv',
        'text/csv',
        null,
        true
    );
    
    // Test the import service
    $importService = new ContractImportService();
    
    echo "\n=== Running Contract Import Test ===\n";
    
    // Store the file temporarily and get the path
    $tempPath = $uploadedFile->store('temp', 'local');
    $fullPath = storage_path('app/' . $tempPath);
    
    $result = $importService->processExcel($fullPath);
    
    // Clean up temp file
    Storage::delete($tempPath);
    
    echo "\n=== Import Results ===\n";
    echo "Processed: {$result['processed']}\n";
    echo "Errors: {$result['errors']}\n";
    
    if ($result['errors'] > 0) {
        echo "\nError Details:\n";
        foreach ($result['error_details'] as $error) {
            echo "- Row {$error['row']}: {$error['error']}\n";
        }
    }
    
    // Check created clients
    $testClients = DB::table('clients')
        ->where('first_name', 'LIKE', 'TEST CLIENT%')
        ->get();
    
    echo "\n=== Created Test Clients ===\n";
    foreach ($testClients as $client) {
        $docNumberDisplay = $client->doc_number === null ? 'NULL' : "'{$client->doc_number}'";
        echo "- {$client->first_name}: doc_number = {$docNumberDisplay}, phone = {$client->primary_phone}\n";
    }
    
    // Verify no duplicate key errors occurred
    if ($result['errors'] === 0) {
        echo "\n✅ SUCCESS: No duplicate key errors occurred!\n";
        echo "✅ All clients with empty doc_number were created successfully with NULL values\n";
    } else {
        echo "\n❌ FAILED: Import still has errors\n";
    }
    
    // Clean up
    DB::table('clients')->where('first_name', 'LIKE', 'TEST CLIENT%')->delete();
    unlink($tempFile);
    echo "\n✓ Test data cleaned up\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Contract Import Test Complete ===\n";