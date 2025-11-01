<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking employees table...\n";
    
    $employees = DB::select('SELECT * FROM employees LIMIT 5');
    echo "Found " . count($employees) . " employees\n";
    
    if (count($employees) > 0) {
        echo "First employee:\n";
        print_r($employees[0]);
    }
    
    echo "\nChecking users table...\n";
    $users = DB::select('SELECT * FROM users LIMIT 5');
    echo "Found " . count($users) . " users\n";
    
    if (count($users) > 0) {
        echo "First user:\n";
        print_r($users[0]);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}