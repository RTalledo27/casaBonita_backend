<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing simple query on contracts table...\n";
    
    // Test basic query
    $result = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT r.client_id) as unique_clients,
            COUNT(DISTINCT c.advisor_id) as active_employees
        ')
        ->first();
    
    echo "Query executed successfully\n";
    echo "Results:\n";
    print_r($result);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}