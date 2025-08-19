<?php

require_once __DIR__ . '/bootstrap/app.php';

use Modules\HumanResources\app\Repositories\CommissionRepository;
use Modules\HumanResources\app\Models\Commission;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DEL TOGGLE 'Mostrar Divisiones' ===\n\n";

$commissionRepo = app(CommissionRepository::class);

// Prueba 1: include_split_payments = false (solo comisiones padre)
echo "1. TOGGLE DESACTIVADO (include_split_payments = false):\n";
echo "   Debería mostrar solo comisiones padre (is_payable = false)\n\n";

$filters1 = ['include_split_payments' => false];
$commissions1 = $commissionRepo->getAll($filters1);

echo "   Total comisiones encontradas: " . $commissions1->count() . "\n";
echo "   Comisiones padre (is_payable = false): " . $commissions1->where('is_payable', false)->count() . "\n";
echo "   Comisiones hijas (is_payable = true): " . $commissions1->where('is_payable', true)->count() . "\n\n";

if ($commissions1->where('is_payable', true)->count() > 0) {
    echo "   ❌ ERROR: Se están mostrando comisiones hijas cuando el toggle está desactivado\n\n";
} else {
    echo "   ✅ CORRECTO: Solo se muestran comisiones padre\n\n";
}

// Prueba 2: include_split_payments = true (todas las comisiones)
echo "2. TOGGLE ACTIVADO (include_split_payments = true):\n";
echo "   Debería mostrar todas las comisiones (padre e hijas)\n\n";

$filters2 = ['include_split_payments' => true];
$commissions2 = $commissionRepo->getAll($filters2);

echo "   Total comisiones encontradas: " . $commissions2->count() . "\n";
echo "   Comisiones padre (is_payable = false): " . $commissions2->where('is_payable', false)->count() . "\n";
echo "   Comisiones hijas (is_payable = true): " . $commissions2->where('is_payable', true)->count() . "\n\n";

if ($commissions2->where('is_payable', true)->count() > 0) {
    echo "   ✅ CORRECTO: Se muestran tanto comisiones padre como hijas\n\n";
} else {
    echo "   ⚠️  ADVERTENCIA: No hay comisiones hijas en el sistema\n\n";
}

// Prueba 3: Sin filtro (comportamiento por defecto)
echo "3. SIN FILTRO (comportamiento por defecto):\n";
echo "   Debería mostrar todas las comisiones\n\n";

$filters3 = [];
$commissions3 = $commissionRepo->getAll($filters3);

echo "   Total comisiones encontradas: " . $commissions3->count() . "\n";
echo "   Comisiones padre (is_payable = false): " . $commissions3->where('is_payable', false)->count() . "\n";
echo "   Comisiones hijas (is_payable = true): " . $commissions3->where('is_payable', true)->count() . "\n\n";

// Mostrar algunas comisiones de ejemplo
echo "=== EJEMPLOS DE COMISIONES ===\n\n";

echo "Comisiones padre (primeras 3):\n";
$parentCommissions = $commissions3->where('is_payable', false)->take(3);
foreach ($parentCommissions as $commission) {
    echo "   ID: {$commission->commission_id}, Employee: {$commission->employee->user->name}, Amount: {$commission->amount}, is_payable: " . ($commission->is_payable ? 'true' : 'false') . "\n";
}

echo "\nComisiones hijas (primeras 3):\n";
$childCommissions = $commissions3->where('is_payable', true)->take(3);
foreach ($childCommissions as $commission) {
    echo "   ID: {$commission->commission_id}, Employee: {$commission->employee->user->name}, Amount: {$commission->amount}, is_payable: " . ($commission->is_payable ? 'true' : 'false') . ", Parent: {$commission->parent_commission_id}\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";