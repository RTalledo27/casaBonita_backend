<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing simple sales query...\n";
    
    $dateFrom = Carbon::now()->subDays(30);
    $dateTo = Carbon::now();
    
    // Test basic contracts query with date filter
    $result = DB::table('contracts as c')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale
        ')->first();
    
    echo "Basic query successful!\n";
    print_r($result);
    
    // Test with reservations join
    echo "\nTesting with reservations join...\n";
    $result2 = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT r.client_id) as unique_clients
        ')->first();
    
    echo "Reservations join successful!\n";
    print_r($result2);
    
    // Test with lots join
    echo "\nTesting with lots join...\n";
    $result3 = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT r.client_id) as unique_clients
        ')->first();
    
    echo "Lots join successful!\n";
    print_r($result3);
    
    // Test with users join
    echo "\nTesting with users join...\n";
    $result4 = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
        ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->selectRaw('
            COUNT(*) as total_sales,
            SUM(c.total_price) as total_revenue,
            AVG(c.total_price) as average_sale,
            COUNT(DISTINCT r.client_id) as unique_clients,
            COUNT(DISTINCT c.advisor_id) as active_employees
        ')->first();
    
    echo "Full query successful!\n";
    print_r($result4);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}