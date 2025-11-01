<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "          📋 ESTRUCTURA COMPLETA DE LA TABLA LOTS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Estructura de la tabla lots
echo "1. COLUMNAS DE LA TABLA LOTS:\n";
echo str_repeat("-", 80) . "\n";
$columns = DB::select('DESCRIBE lots');

printf("%-30s | %-20s | %-10s | %-10s | %s\n", "CAMPO", "TIPO", "NULL", "KEY", "DEFAULT");
echo str_repeat("-", 80) . "\n";

foreach ($columns as $col) {
    printf(
        "%-30s | %-20s | %-10s | %-10s | %s\n",
        $col->Field,
        $col->Type,
        $col->Null,
        $col->Key ?? '',
        $col->Default ?? 'NULL'
    );
}

// 2. Relaciones (Foreign Keys)
echo "\n\n2. RELACIONES / FOREIGN KEYS:\n";
echo str_repeat("-", 80) . "\n";

$foreignKeys = DB::select("
    SELECT 
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE 
        TABLE_NAME = 'lots' 
        AND TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if (empty($foreignKeys)) {
    echo "  ⚠️  No hay foreign keys definidas explícitamente\n";
} else {
    foreach ($foreignKeys as $fk) {
        echo "  • {$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
    }
}

// 3. Índices
echo "\n\n3. ÍNDICES:\n";
echo str_repeat("-", 80) . "\n";

$indexes = DB::select("SHOW INDEXES FROM lots");
$indexGroups = [];

foreach ($indexes as $idx) {
    if (!isset($indexGroups[$idx->Key_name])) {
        $indexGroups[$idx->Key_name] = [];
    }
    $indexGroups[$idx->Key_name][] = $idx->Column_name;
}

foreach ($indexGroups as $indexName => $columns) {
    $type = ($indexName === 'PRIMARY') ? 'PRIMARY KEY' : 'INDEX';
    echo "  • {$type}: {$indexName} → (" . implode(', ', $columns) . ")\n";
}

// 4. Datos de ejemplo
echo "\n\n4. EJEMPLO DE DATOS (primeros 3 lotes):\n";
echo str_repeat("-", 80) . "\n";

$sampleLots = DB::table('lots')->limit(3)->get();

foreach ($sampleLots as $lot) {
    echo "\n  LOTE ID: {$lot->lot_id}\n";
    foreach ($lot as $key => $value) {
        $displayValue = $value ?? 'NULL';
        if (strlen($displayValue) > 50) {
            $displayValue = substr($displayValue, 0, 47) . '...';
        }
        echo "    {$key}: {$displayValue}\n";
    }
}

// 5. Relaciones con otras tablas
echo "\n\n5. RELACIONES CON OTRAS TABLAS:\n";
echo str_repeat("-", 80) . "\n";

echo "\n  A) Lotes → Manzanas:\n";
$manzanaExample = DB::table('lots as l')
    ->join('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
    ->select('l.lot_id', 'l.num_lot', 'm.manzana_id', 'm.name as manzana_name')
    ->limit(3)
    ->get();

foreach ($manzanaExample as $item) {
    echo "    Lote {$item->num_lot} → Manzana '{$item->manzana_name}' (ID: {$item->manzana_id})\n";
}

echo "\n  B) Lotes → Street Types (si existe):\n";
$streetTypeExample = DB::table('lots as l')
    ->leftJoin('street_types as st', 'l.street_type_id', '=', 'st.street_type_id')
    ->select('l.lot_id', 'l.num_lot', 'st.street_type_id', 'st.name as street_type_name')
    ->whereNotNull('l.street_type_id')
    ->limit(3)
    ->get();

if ($streetTypeExample->isEmpty()) {
    echo "    ⚠️  No hay lotes con street_type_id asignado\n";
} else {
    foreach ($streetTypeExample as $item) {
        echo "    Lote {$item->num_lot} → Tipo Calle '{$item->street_type_name}' (ID: {$item->street_type_id})\n";
    }
}

echo "\n  C) Lotes usados en Contratos:\n";
$contractsExample = DB::table('contracts as c')
    ->join('lots as l', 'c.lot_id', '=', 'l.lot_id')
    ->select('c.contract_id', 'c.contract_number', 'l.num_lot', 'l.lot_id')
    ->limit(3)
    ->get();

if ($contractsExample->isEmpty()) {
    echo "    ⚠️  No hay contratos vinculados con lotes\n";
} else {
    foreach ($contractsExample as $item) {
        echo "    Contrato {$item->contract_number} → Lote {$item->num_lot} (lot_id: {$item->lot_id})\n";
    }
}

echo "\n  D) Lotes usados en Reservaciones:\n";
$reservationsExample = DB::table('reservations as r')
    ->join('lots as l', 'r.lot_id', '=', 'l.lot_id')
    ->select('r.reservation_id', 'r.reservation_date', 'l.num_lot', 'l.lot_id')
    ->limit(3)
    ->get();

if ($reservationsExample->isEmpty()) {
    echo "    ⚠️  No hay reservaciones vinculadas con lotes\n";
} else {
    foreach ($reservationsExample as $item) {
        echo "    Reservación ID {$item->reservation_id} ({$item->reservation_date}) → Lote {$item->num_lot}\n";
    }
}

// 6. Estadísticas
echo "\n\n6. ESTADÍSTICAS:\n";
echo str_repeat("-", 80) . "\n";

$stats = DB::table('lots')->selectRaw('
    COUNT(*) as total_lotes,
    COUNT(DISTINCT manzana_id) as total_manzanas,
    COUNT(DISTINCT status) as diferentes_estados,
    AVG(area_m2) as area_promedio_m2,
    SUM(CASE WHEN total_price > 0 THEN 1 ELSE 0 END) as lotes_con_precio
')->first();

echo "  Total de lotes: {$stats->total_lotes}\n";
echo "  Total de manzanas diferentes: {$stats->total_manzanas}\n";
echo "  Diferentes estados: {$stats->diferentes_estados}\n";
echo "  Área promedio: " . round($stats->area_promedio_m2, 2) . " m²\n";
echo "  Lotes con precio: {$stats->lotes_con_precio}\n";

// 7. Estados de lotes
echo "\n\n7. ESTADOS DE LOTES:\n";
echo str_repeat("-", 80) . "\n";

$statusDistribution = DB::table('lots')
    ->select('status', DB::raw('COUNT(*) as cantidad'))
    ->groupBy('status')
    ->orderBy('cantidad', 'DESC')
    ->get();

foreach ($statusDistribution as $status) {
    $percentage = round(($status->cantidad / $stats->total_lotes) * 100, 1);
    echo "  • {$status->status}: {$status->cantidad} lotes ({$percentage}%)\n";
}

// 8. Campos calculados o derivados comunes
echo "\n\n8. CAMPOS CALCULADOS/DERIVADOS COMUNES:\n";
echo str_repeat("-", 80) . "\n";
echo "  • Precio por m²: total_price / area_m2\n";
echo "  • Identificador completo: CONCAT(manzana.name, '-', num_lot)\n";
echo "  • Estado de disponibilidad: Basado en campo 'status'\n";
echo "  • Área total construida: area_construction_m2 (si existe)\n";

echo "\n\n═══════════════════════════════════════════════════════════════\n";
echo "                    ✅ ANÁLISIS COMPLETO\n";
echo "═══════════════════════════════════════════════════════════════\n";
