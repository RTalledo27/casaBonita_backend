<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing basic contracts query...\n";
    
    // Test 1: Basic contracts table
    $contracts = DB::table('contracts')->count();
    echo "Total contracts: $contracts\n";
    
    // Test 2: Contracts with reservations join
    $contractsWithReservations = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->count();
    echo "Contracts with reservations join: $contractsWithReservations\n";
    
    // Test 3: Add lots join
    $contractsWithLots = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->count();
    echo "Contracts with lots join: $contractsWithLots\n";
    
    // Test 4: Add projects join
    $contractsWithProjects = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('projects as p', 'l.project_id', '=', 'p.project_id')
        ->count();
    echo "Contracts with projects join: $contractsWithProjects\n";
    
    // Test 5: Add users join
    $contractsWithUsers = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('projects as p', 'l.project_id', '=', 'p.project_id')
        ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
        ->count();
    echo "Contracts with users join: $contractsWithUsers\n";
    
    // Test 6: Add date filter
    $dateFrom = Carbon::now()->subDays(30);
    $dateTo = Carbon::now();
    
    $contractsWithDate = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('projects as p', 'l.project_id', '=', 'p.project_id')
        ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->count();
    echo "Contracts with date filter: $contractsWithDate\n";
    
    // Test 7: Add status filter
    $contractsWithStatus = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('projects as p', 'l.project_id', '=', 'p.project_id')
        ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
        ->whereBetween('c.created_at', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->count();
    echo "Contracts with status filter: $contractsWithStatus\n";
    
    // Test 8: Try the selectRaw
    echo "Testing selectRaw query...\n";
    $result = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('projects as p', 'l.project_id', '=', 'p.project_id')
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
    
    echo "Query successful!\n";
    print_r($result);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}