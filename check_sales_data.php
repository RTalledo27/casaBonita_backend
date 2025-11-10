<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;

echo "=== VERIFICANDO ASESORES ===\n\n";

// Buscar JAVIER CORDOVA CORDOVA (usando relación con users)
$javier = Employee::whereHas('user', function($q) {
    $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", ['JAVIER CORDOVA CORDOVA']);
})->with('user')->first();
echo "JAVIER CORDOVA CORDOVA: " . ($javier ? "✅ ID: {$javier->employee_id} (User: {$javier->user->first_name} {$javier->user->last_name})" : "❌ NO EXISTE") . "\n";

// Buscar JESUS JUAREZ JUAREZ
$jesus = Employee::whereHas('user', function($q) {
    $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", ['JESUS JUAREZ JUAREZ']);
})->with('user')->first();
echo "JESUS JUAREZ JUAREZ: " . ($jesus ? "✅ ID: {$jesus->employee_id} (User: {$jesus->user->first_name} {$jesus->user->last_name})" : "❌ NO EXISTE") . "\n";

echo "\n=== VERIFICANDO LOTES ===\n\n";

// Buscar G-60
$manzanaG = Manzana::where('name', 'G')->first();
if ($manzanaG) {
    $g60 = Lot::where('manzana_id', $manzanaG->manzana_id)->where('num_lot', 60)->first();
    echo "G-60: " . ($g60 ? "✅ ID: {$g60->lot_id}, Status: {$g60->availability_status}" : "❌ NO EXISTE") . "\n";
} else {
    echo "Manzana G: ❌ NO EXISTE\n";
}

// Buscar H-67
$manzanaH = Manzana::where('name', 'H')->first();
if ($manzanaH) {
    $h67 = Lot::where('manzana_id', $manzanaH->manzana_id)->where('num_lot', 67)->first();
    echo "H-67: " . ($h67 ? "✅ ID: {$h67->lot_id}, Status: {$h67->availability_status}" : "❌ NO EXISTE") . "\n";
} else {
    echo "Manzana H: ❌ NO EXISTE\n";
}

echo "\n=== LISTANDO ALGUNOS ASESORES DISPONIBLES ===\n\n";
$asesores = Employee::with('user')->limit(10)->get();

foreach ($asesores as $asesor) {
    $userName = $asesor->user ? "{$asesor->user->first_name} {$asesor->user->last_name}" : "Sin usuario";
    echo "• {$userName} (Employee ID: {$asesor->employee_id}, Type: {$asesor->employee_type})\n";
}

echo "\n=== LISTANDO ALGUNAS MANZANAS DISPONIBLES ===\n\n";
$manzanas = Manzana::limit(10)->get(['manzana_id', 'name']);
foreach ($manzanas as $manzana) {
    $countLots = Lot::where('manzana_id', $manzana->manzana_id)->count();
    echo "• Manzana {$manzana->name} (ID: {$manzana->manzana_id}) - {$countLots} lotes\n";
}
