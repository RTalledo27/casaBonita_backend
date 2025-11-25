<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ADVISOR IDs CON VENTAS EN OCTUBRE 2025 ===\n\n";
echo "Por favor identifica el employee_id de Luis Tavara:\n\n";

$results = DB::select("
    SELECT 
        advisor_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'vigente' AND financing_amount > 0 THEN 1 ELSE 0 END) as counted
    FROM contracts
    WHERE MONTH(sign_date) = 10
    AND YEAR(sign_date) = 2025
    GROUP BY advisor_id
    ORDER BY counted DESC
");

foreach ($results as $row) {
    echo sprintf("Advisor ID: %3d | Total: %2d | Contados: %2d\n", 
        $row->advisor_id, 
        $row->total, 
        $row->counted
    );
    
    if ($row->counted == 21) {
        echo "       ^^^ Este podría ser Luis Tavara (21 ventas contadas) ^^^\n";
    }
}

echo "\n\nUna vez que identifiques el ID, puedo hacer el análisis completo.\n";
