<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BÚSQUEDA DE EMPLOYEE_ID CON PATRÓN '1822' ===\n\n";

// Buscar en employees con patrón 1822
$employees = DB::select("
    SELECT employee_id 
    FROM employees 
    WHERE employee_id LIKE '%1822%'
");

if (!empty($employees)) {
    echo "Employees encontrados con '1822':\n";
    foreach ($employees as $emp) {
        echo "  - {$emp->employee_id}\n";
        
        // Ver cuántos contratos tiene en octubre
        $count = DB::table('contracts')
            ->where('advisor_id', $emp->employee_id)
            ->whereMonth('sign_date', 10)
            ->whereYear('sign_date', 2025)
            ->count();
        
        echo "    Contratos en octubre: $count\n";
    }
} else {
    echo "No se encontró ningún employee_id con '1822'\n\n";
    echo "Mostrando advisors con más de 15 contratos en octubre:\n\n";
    
    $topAdvisors = DB::select("
        SELECT 
            advisor_id,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'vigente' AND financing_amount > 0 THEN 1 ELSE 0 END) as counted
        FROM contracts
        WHERE MONTH(sign_date) = 10
        AND YEAR(sign_date) = 2025
        GROUP BY advisor_id
        HAVING total >= 15
        ORDER BY counted DESC
    ");
    
    foreach ($topAdvisors as $adv) {
        echo sprintf("ID: %s | Total: %d | Contados: %d", 
            $adv->advisor_id, $adv->total, $adv->counted);
        
        if ($adv->counted == 21) {
            echo " <-- ESTE TIENE 21 (Luis Tavara?)";
        }
        echo "\n";
    }
}
