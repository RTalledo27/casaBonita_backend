<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;

echo "=== Finding Available Advisors ===\n\n";

try {
    $advisors = Employee::with('user')
        ->where('employee_type', 'asesor_inmobiliario')
        ->take(5)
        ->get();
    
    echo "Found " . $advisors->count() . " advisors:\n";
    
    foreach ($advisors as $advisor) {
        $fullName = $advisor->user->first_name . ' ' . $advisor->user->last_name;
        echo "- ID: {$advisor->employee_id}, Code: {$advisor->employee_code}, Name: {$fullName}\n";
    }
    
    if ($advisors->count() > 0) {
        $firstAdvisor = $advisors->first();
        $advisorName = $firstAdvisor->user->first_name . ' ' . $firstAdvisor->user->last_name;
        echo "\n✓ Use this advisor name in test: '{$advisorName}'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";