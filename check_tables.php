<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking database tables...\n";
    
    $tables = DB::select('SHOW TABLES');
    echo "Found " . count($tables) . " tables\n\n";
    
    echo "Looking for employee/user/advisor related tables:\n";
    foreach($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if(strpos($tableName, 'employee') !== false || 
           strpos($tableName, 'user') !== false || 
           strpos($tableName, 'advisor') !== false) {
            echo "- " . $tableName . "\n";
        }
    }
    
    echo "\nChecking contracts table structure:\n";
    $contractColumns = DB::select("DESCRIBE contracts");
    foreach($contractColumns as $column) {
        echo "- " . $column->Field . " (" . $column->Type . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}