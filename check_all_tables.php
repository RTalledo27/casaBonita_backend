<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Listing all tables in the database...\n";
    
    $tables = DB::select('SHOW TABLES');
    
    foreach ($tables as $table) {
        $tableName = array_values((array) $table)[0];
        echo "- $tableName\n";
    }
    
    echo "\nChecking specific tables structure:\n";
    
    // Check contracts table
    echo "\nContracts table structure:\n";
    $contractsColumns = DB::select('DESCRIBE contracts');
    foreach ($contractsColumns as $column) {
        echo "  {$column->Field} ({$column->Type})\n";
    }
    
    // Check reservations table
    echo "\nReservations table structure:\n";
    $reservationsColumns = DB::select('DESCRIBE reservations');
    foreach ($reservationsColumns as $column) {
        echo "  {$column->Field} ({$column->Type})\n";
    }
    
    // Check lots table
    echo "\nLots table structure:\n";
    $lotsColumns = DB::select('DESCRIBE lots');
    foreach ($lotsColumns as $column) {
        echo "  {$column->Field} ({$column->Type})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}