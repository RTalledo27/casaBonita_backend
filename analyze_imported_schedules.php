<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ANÁLISIS DE CUOTAS IMPORTADAS ===\n\n";

// Obtener un contrato reciente
$contract = DB::table('contracts')
    ->where('source', 'logicware')
    ->orderBy('contract_id', 'desc')
    ->first();

if (!$contract) {
    echo "❌ No se encontraron contratos importados\n";
    exit;
}

echo "📋 Contrato: {$contract->contract_number}\n";
echo "   ID: {$contract->contract_id}\n\n";

echo "💰 DATOS FINANCIEROS EN CONTRATO:\n";
echo "   - Total Price: S/ " . number_format($contract->total_price, 2) . "\n";
echo "   - Discount: S/ " . number_format($contract->discount ?? 0, 2) . "\n";
echo "   - Down Payment: S/ " . number_format($contract->down_payment, 2) . "\n";
echo "   - Financing Amount: S/ " . number_format($contract->financing_amount, 2) . "\n";
echo "   - Balloon Payment: S/ " . number_format($contract->balloon_payment ?? 0, 2) . " " . ($contract->balloon_payment > 0 ? '✅' : '❌') . "\n";
echo "   - BPP: S/ " . number_format($contract->bpp ?? 0, 2) . " " . ($contract->bpp > 0 ? '✅' : '❌') . "\n";
echo "   - Monthly Payment: S/ " . number_format($contract->monthly_payment, 2) . "\n";
echo "   - Term: {$contract->term_months} meses\n\n";

echo "📅 CRONOGRAMA DE PAGOS GENERADO:\n";
echo "================================\n\n";

$schedules = DB::table('payment_schedules')
    ->where('contract_id', $contract->contract_id)
    ->orderBy('installment_number')
    ->get();

echo "Total de cuotas: " . count($schedules) . "\n\n";

$types = [
    'inicial' => 0,
    'financiamiento' => 0,
    'balon' => 0,
    'bono_bpp' => 0,
    'otros' => 0
];

foreach ($schedules as $schedule) {
    $type = $schedule->type ?? 'sin_tipo';
    
    if ($type == 'balon' || $type == 'bono_bpp' || $schedule->installment_number > 100) {
        echo sprintf(
            "Cuota %3d: %-20s S/ %10s - %s %s\n",
            $schedule->installment_number,
            $type,
            number_format($schedule->amount, 2),
            $schedule->due_date,
            $schedule->notes ?? ''
        );
    }
    
    if (isset($types[$type])) {
        $types[$type]++;
    } else {
        $types['otros']++;
    }
}

echo "\n📊 RESUMEN POR TIPO:\n";
foreach ($types as $type => $count) {
    if ($count > 0) {
        echo "   - " . ucfirst($type) . ": {$count} cuotas\n";
    }
}

// Buscar cuotas de balón y BPP específicamente
$balonCuota = DB::table('payment_schedules')
    ->where('contract_id', $contract->contract_id)
    ->where('type', 'balon')
    ->first();

$bppCuota = DB::table('payment_schedules')
    ->where('contract_id', $contract->contract_id)
    ->where('type', 'bono_bpp')
    ->first();

echo "\n🔍 VERIFICACIÓN ESPECÍFICA:\n";
echo "   - ¿Existe cuota balón en BD? " . ($balonCuota ? '✅ SÍ' : '❌ NO') . "\n";
echo "   - ¿Existe cuota BPP en BD? " . ($bppCuota ? '✅ SÍ' : '❌ NO') . "\n";

if (!$balonCuota && $contract->balloon_payment > 0) {
    echo "\n⚠️ PROBLEMA: El contrato tiene balloon_payment pero NO se generó la cuota\n";
}

if (!$bppCuota && $contract->bpp > 0) {
    echo "\n⚠️ PROBLEMA: El contrato tiene bpp pero NO se generó la cuota\n";
}

// Verificar datos de Logicware
if ($contract->logicware_data) {
    echo "\n📦 DATOS ORIGINALES DE LOGICWARE:\n";
    $data = json_decode($contract->logicware_data, true);
    
    if (isset($data['financing'])) {
        echo "   Financing keys: " . implode(', ', array_keys($data['financing'])) . "\n";
        
        // Buscar campos de balón
        foreach ($data['financing'] as $key => $value) {
            if (stripos($key, 'balloon') !== false || stripos($key, 'balon') !== false) {
                echo "   - {$key}: {$value}\n";
            }
            if (stripos($key, 'bpp') !== false || stripos($key, 'bono') !== false) {
                echo "   - {$key}: {$value}\n";
            }
        }
    }
}

echo "\n✅ Análisis completado\n";
