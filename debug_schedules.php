<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 ANÁLISIS DE CRONOGRAMAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$contracts = DB::table('contracts')->where('source', 'logicware')->get();

echo "📊 Total contratos: " . $contracts->count() . "\n\n";

foreach ($contracts->take(3) as $contract) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📄 Contrato: {$contract->contract_number}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $schedules = DB::table('payment_schedules')
        ->where('contract_id', $contract->contract_id)
        ->orderBy('installment_number')
        ->get();
    
    echo "Total cuotas: " . $schedules->count() . "\n\n";
    
    $byType = $schedules->groupBy('type');
    
    echo "📋 Por tipo:\n";
    foreach ($byType as $type => $items) {
        echo "  - {$type}: " . $items->count() . "\n";
    }
    
    echo "\n📅 Primeras 5 cuotas:\n";
    foreach ($schedules->take(5) as $schedule) {
        echo "  Cuota #{$schedule->installment_number}: ";
        echo "Tipo: {$schedule->type} | ";
        echo "Monto: S/ " . number_format($schedule->amount, 2) . " | ";
        echo "Estado: {$schedule->status} | ";
        echo "Nota: " . ($schedule->notes ?? 'N/A') . "\n";
    }
    
    echo "\n🎈 Cuotas Balón:\n";
    $balloons = $schedules->where('type', 'balon');
    if ($balloons->count() > 0) {
        foreach ($balloons as $balloon) {
            echo "  Cuota #{$balloon->installment_number}: S/ " . number_format($balloon->amount, 2);
            echo " | Vence: {$balloon->due_date}";
            echo " | Nota: " . ($balloon->notes ?? 'N/A') . "\n";
        }
    } else {
        echo "  ❌ No hay cuotas balón\n";
    }
    
    echo "\n🎁 Bonos BPP:\n";
    $bpps = $schedules->where('type', 'bono_bpp');
    if ($bpps->count() > 0) {
        foreach ($bpps as $bpp) {
            echo "  Cuota #{$bpp->installment_number}: S/ " . number_format($bpp->amount, 2);
            echo " | Vence: {$bpp->due_date}";
            echo " | Nota: " . ($bpp->notes ?? 'N/A') . "\n";
        }
    } else {
        echo "  ❌ No hay bonos BPP\n";
    }
    
    echo "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 RESUMEN GLOBAL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$totalSchedules = DB::table('payment_schedules')
    ->whereIn('contract_id', $contracts->pluck('contract_id'))
    ->count();

$byType = DB::table('payment_schedules')
    ->whereIn('contract_id', $contracts->pluck('contract_id'))
    ->select('type', DB::raw('count(*) as total'))
    ->groupBy('type')
    ->get();

echo "Total de cuotas: {$totalSchedules}\n\n";
echo "Por tipo:\n";
foreach ($byType as $item) {
    echo "  - {$item->type}: {$item->total}\n";
}

$paidSchedules = DB::table('payment_schedules')
    ->whereIn('contract_id', $contracts->pluck('contract_id'))
    ->where('status', 'pagado')
    ->count();

echo "\n✅ Cuotas pagadas: {$paidSchedules}\n";
