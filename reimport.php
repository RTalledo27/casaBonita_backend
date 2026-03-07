<?php
try {
    echo "Starting Logicware import...\n";
    $importer = app(\App\Services\LogicwareContractImporter::class);
    $result = $importer->importContracts(null, null, false);
    file_put_contents('import_result.txt', json_encode($result, JSON_PRETTY_PRINT));
    echo "Done. Total sales: " . ($result['total_sales'] ?? 0) . "\n";
    echo "Contracts created: " . ($result['contracts_created'] ?? 0) . "\n";
    echo "Contracts skipped: " . ($result['contracts_skipped'] ?? 0) . "\n";
    echo "Errors: " . count($result['errors'] ?? []) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    file_put_contents('import_result.txt', "ERROR: " . $e->getMessage());
}
