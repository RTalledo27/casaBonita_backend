<?php
try {
    echo "Starting import...\n";
    // Not using true for force_refresh to avoid 400 bad request from logicware limit
    $result = app(\App\Services\LogicwareContractImporter::class)->importContracts('2024-01-01', '2027-01-01', false);
    echo "Import finished. Total sales: " . ($result['total_sales'] ?? 0) . "\n";
    echo "Contracts created: " . ($result['contracts_created'] ?? $result['created_count'] ?? 0) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
