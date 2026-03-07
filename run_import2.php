<?php
try {
    echo "Starting import...\n";
    $result = app(\App\Services\LogicwareContractImporter::class)->importContracts('2024-01-01', '2027-01-01', true);
    echo "Import finished. Total sales: " . ($result['total_sales'] ?? 0) . "\n";
    echo "Contracts created: " . ($result['contracts_created'] ?? 0) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
