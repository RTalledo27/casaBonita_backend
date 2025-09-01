<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Verificando migración de campos financieros...\n\n";

// 1. Verificar que los campos fueron removidos de lots
echo "📋 Verificando tabla 'lots':\n";
$lotColumns = Schema::getColumnListing('lots');
$removedFields = ['funding', 'BPP', 'BFH', 'initial_quota'];

foreach ($removedFields as $field) {
    if (in_array($field, $lotColumns)) {
        echo "❌ ERROR: Campo '{$field}' aún existe en tabla lots\n";
    } else {
        echo "✅ OK: Campo '{$field}' removido de tabla lots\n";
    }
}

// 2. Verificar que los campos fueron agregados a contracts
echo "\n📋 Verificando tabla 'contracts':\n";
$contractColumns = Schema::getColumnListing('contracts');
$newFields = ['bpp', 'bfh', 'initial_quota'];

foreach ($newFields as $field) {
    if (in_array($field, $contractColumns)) {
        echo "✅ OK: Campo '{$field}' existe en tabla contracts\n";
    } else {
        echo "❌ ERROR: Campo '{$field}' no existe en tabla contracts\n";
    }
}

// 3. Verificar que funding existe como financing_amount
if (in_array('financing_amount', $contractColumns)) {
    echo "✅ OK: Campo 'financing_amount' existe en tabla contracts\n";
} else {
    echo "❌ ERROR: Campo 'financing_amount' no existe en tabla contracts\n";
}

// 4. Verificar integridad de datos
echo "\n📊 Verificando integridad de datos:\n";
try {
    $contractsWithFinancialData = DB::table('contracts')
        ->where(function($query) {
            $query->where('bpp', '>', 0)
                  ->orWhere('bfh', '>', 0)
                  ->orWhere('initial_quota', '>', 0)
                  ->orWhere('financing_amount', '>', 0);
        })
        ->count();
    
    echo "📈 Contratos con datos financieros: {$contractsWithFinancialData}\n";
    
    $totalContracts = DB::table('contracts')->count();
    echo "📊 Total de contratos: {$totalContracts}\n";
    
    if ($contractsWithFinancialData > 0) {
        echo "✅ OK: Datos financieros encontrados en contratos\n";
    } else {
        echo "⚠️  ADVERTENCIA: No se encontraron datos financieros en contratos\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR al verificar datos: " . $e->getMessage() . "\n";
}

// 5. Verificar que los modelos están actualizados
echo "\n🔧 Verificando modelos:\n";
try {
    // Verificar modelo Lot
    $lotModel = new \Modules\Inventory\Models\Lot();
    $lotFillable = $lotModel->getFillable();
    
    $hasFinancialFields = false;
    foreach ($removedFields as $field) {
        if (in_array($field, $lotFillable) || in_array(strtolower($field), $lotFillable)) {
            $hasFinancialFields = true;
            echo "❌ ERROR: Campo '{$field}' aún está en fillable de Lot\n";
        }
    }
    
    if (!$hasFinancialFields) {
        echo "✅ OK: Modelo Lot no tiene campos financieros en fillable\n";
    }
    
    // Verificar modelo Contract
    $contractModel = new \Modules\Sales\Models\Contract();
    $contractFillable = $contractModel->getFillable();
    
    $hasAllFinancialFields = true;
    foreach (['bpp', 'bfh', 'initial_quota'] as $field) {
        if (!in_array($field, $contractFillable)) {
            $hasAllFinancialFields = false;
            echo "❌ ERROR: Campo '{$field}' no está en fillable de Contract\n";
        }
    }
    
    if ($hasAllFinancialFields) {
        echo "✅ OK: Modelo Contract tiene todos los campos financieros en fillable\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR al verificar modelos: " . $e->getMessage() . "\n";
}

echo "\n🎉 Verificación completada\n";
echo "\n📝 Próximos pasos recomendados:\n";
echo "1. Ejecutar tests de la aplicación\n";
echo "2. Verificar endpoints API de lotes y contratos\n";
echo "3. Probar funcionalidad de importación de contratos\n";
echo "4. Verificar frontend si es necesario\n";
echo "5. Ejecutar migración de limpieza si todo está correcto\n";