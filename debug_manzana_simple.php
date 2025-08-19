<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\Lot;

$output = "=== DEBUG MANZANA LOOKUP ISSUES ===\n\n";

// 1. Mostrar todas las manzanas disponibles
$output .= "1. MANZANAS DISPONIBLES EN LA BASE DE DATOS:\n";
$manzanas = Manzana::orderBy('name')->get();
foreach ($manzanas as $manzana) {
    $output .= "   - ID: {$manzana->manzana_id}, Nombre: '{$manzana->name}'\n";
}

$output .= "\n2. BÚSQUEDAS ESPECÍFICAS DEL EXCEL:\n";

// 2. Buscar las manzanas específicas del Excel que están fallando
$searchTerms = ['manzana 2', 'manzana 3', '2', '3'];

foreach ($searchTerms as $term) {
    $output .= "\n   Buscando: '{$term}'\n";
    
    // Búsqueda exacta (como hace el código actual)
    $exactMatch = Manzana::where('name', $term)->first();
    $output .= "   - Búsqueda exacta: " . ($exactMatch ? "ENCONTRADA (ID: {$exactMatch->manzana_id})" : "NO ENCONTRADA") . "\n";
    
    // Búsqueda con LIKE (más flexible)
    $likeMatches = Manzana::where('name', 'LIKE', "%{$term}%")->get();
    $output .= "   - Búsqueda LIKE: " . ($likeMatches->count() > 0 ? "ENCONTRADAS ({$likeMatches->count()})" : "NO ENCONTRADAS") . "\n";
    
    if ($likeMatches->count() > 0) {
        foreach ($likeMatches as $match) {
            $output .= "     * ID: {$match->manzana_id}, Nombre: '{$match->name}'\n";
        }
    }
}

// Write to file
file_put_contents('debug_manzana_results.txt', $output);
echo "Results written to debug_manzana_results.txt\n";