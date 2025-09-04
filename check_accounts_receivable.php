<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Checking accounts_receivable table ===\n";

try {
    // Check if table exists
    if (Schema::hasTable('accounts_receivable')) {
        echo "✓ Table 'accounts_receivable' exists\n";
        
        // Get table structure
        $columns = Schema::getColumnListing('accounts_receivable');
        echo "Columns: " . implode(', ', $columns) . "\n";
        
        // Count records
        $count = DB::table('accounts_receivable')->count();
        echo "Total records: $count\n";
        
        if ($count > 0) {
            echo "\n=== Sample records ===\n";
            $sample = DB::table('accounts_receivable')
                ->limit(3)
                ->get();
            
            foreach ($sample as $record) {
                echo json_encode($record, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "\n⚠️  Table is empty - this explains why the frontend shows no data\n";
        }
        
    } else {
        echo "❌ Table 'accounts_receivable' does not exist\n";
        echo "This explains why the API endpoint fails\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Checking Collections module routes ===\n";
try {
    $routes = app('router')->getRoutes();
    $collectionsRoutes = [];
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'collections') !== false) {
            $collectionsRoutes[] = $uri;
        }
    }
    
    if (!empty($collectionsRoutes)) {
        echo "Collections routes found:\n";
        foreach ($collectionsRoutes as $route) {
            echo "- $route\n";
        }
    } else {
        echo "No collections routes found\n";
    }
    
} catch (Exception $e) {
    echo "Error checking routes: " . $e->getMessage() . "\n";
}