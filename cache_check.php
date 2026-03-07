<?php
try {
    $api = app(\App\Services\LogicwareApiService::class);
    $sales = $api->getSales(null, null, false);
    $data = $sales['data'] ?? [];
    
    $output = "Total sales from cache: " . count($data) . "\n\n";
    
    foreach (array_slice($data, 0, 3) as $i => $sale) {
        $output .= "=== Sale $i ===\n";
        $output .= "documentNumber: " . ($sale['documentNumber'] ?? 'N/A') . "\n";
        $output .= "documents count: " . count($sale['documents'] ?? []) . "\n";
        foreach ($sale['documents'] ?? [] as $j => $doc) {
            $output .= "  Doc $j: status=" . ($doc['status'] ?? 'N/A') . " correlative=" . ($doc['correlative'] ?? 'N/A') . " units=" . count($doc['units'] ?? []) . "\n";
        }
        $output .= "\n";
    }
    
    file_put_contents('cache_check.txt', $output);
    echo "Done\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
