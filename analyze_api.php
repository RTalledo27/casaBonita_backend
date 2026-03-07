<?php
// Capture raw Logicware API data for analysis
try {
    $api = app(\App\Services\LogicwareApiService::class);
    
    // Call the API with dates Jan 1 - Mar 31 2026
    $startDate = '2026-01-01';
    $endDate = '2026-03-31';
    
    echo "Calling Logicware API for $startDate to $endDate...\n";
    $sales = $api->getSales($startDate, $endDate, true); // force refresh
    $data = $sales['data'] ?? [];
    
    echo "Total sales returned: " . count($data) . "\n\n";
    
    // Collect all unique status values and correlative patterns
    $statuses = [];
    $correlativePatterns = [];
    $sampleDocs = [];
    
    foreach ($data as $sale) {
        foreach ($sale['documents'] ?? [] as $doc) {
            $status = $doc['status'] ?? 'N/A';
            $correlative = $doc['correlative'] ?? 'N/A';
            $seller = $doc['seller'] ?? 'N/A';
            
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            
            // Extract correlative pattern (first part before numbers)
            $pattern = preg_replace('/\d+/', '#', $correlative);
            $correlativePatterns[$pattern] = ($correlativePatterns[$pattern] ?? 0) + 1;
            
            // Save first 3 sample docs per status
            if (!isset($sampleDocs[$status]) || count($sampleDocs[$status]) < 2) {
                $sampleDocs[$status][] = [
                    'correlative' => $correlative,
                    'seller' => $seller,
                    'status' => $status,
                    'saleStartDate' => $doc['saleStartDate'] ?? null,
                    'separationStartDate' => $doc['separationStartDate'] ?? null,
                    'units_count' => count($doc['units'] ?? []),
                    'unit_numbers' => array_map(fn($u) => $u['unitNumber'] ?? 'N/A', $doc['units'] ?? []),
                ];
            }
        }
    }
    
    $output = [
        'total_sales' => count($data),
        'unique_statuses' => $statuses,
        'correlative_patterns' => $correlativePatterns,
        'sample_docs_by_status' => $sampleDocs,
    ];
    
    // Also collect unique seller names
    $sellers = [];
    foreach ($data as $sale) {
        foreach ($sale['documents'] ?? [] as $doc) {
            $seller = $doc['seller'] ?? null;
            if ($seller) $sellers[$seller] = ($sellers[$seller] ?? 0) + 1;
        }
    }
    $output['unique_sellers'] = $sellers;
    
    file_put_contents('api_analysis.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Analysis saved to api_analysis.json\n";
    echo "\n=== STATUS VALUES ===\n";
    foreach ($statuses as $status => $count) {
        echo "  '$status': $count documents\n";
    }
    echo "\n=== SELLERS ===\n";
    foreach ($sellers as $seller => $count) {
        echo "  '$seller': $count documents\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
