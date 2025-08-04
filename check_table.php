<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $columns = DB::select('DESCRIBE commissions');
    echo "Commissions table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type})\n";
    }
    
    echo "\nChecking for split payment fields:\n";
    $splitFields = ['commission_period', 'payment_period', 'status', 'payment_percentage', 'parent_commission_id', 'payment_part', 'payment_type'];
    
    foreach ($splitFields as $field) {
        $exists = collect($columns)->pluck('Field')->contains($field);
        echo "- {$field}: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}