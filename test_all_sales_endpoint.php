<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n🔍 Testeando endpoint /v1/reports/sales/all\n";
echo "=============================================\n\n";

// Simulate the repository call
$dateFrom = '2024-01-01';
$dateTo = '2025-12-31';
$limit = 10;
$offset = 0;

echo "Parámetros:\n";
echo "  • date_from: {$dateFrom}\n";
echo "  • date_to: {$dateTo}\n";
echo "  • limit: {$limit}\n\n";

try {
    $sales = DB::table('contracts as c')
        ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
        ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'r.lot_id', '=', 'l.lot_id')
        ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
        ->whereBetween('c.sign_date', [$dateFrom, $dateTo])
        ->where('c.status', 'vigente')
        ->selectRaw('
            c.contract_id,
            c.contract_number,
            c.sign_date,
            c.total_price,
            c.down_payment,
            c.financing_amount,
            c.term_months,
            c.monthly_payment,
            c.interest_rate,
            c.status,
            e.employee_id,
            CONCAT(u.first_name, " ", u.last_name) as advisor_name,
            c.client_id,
            l.lot_id,
            l.num_lot as lot_number,
            m.name as manzana_name,
            l.area_m2 as lot_area,
            l.total_price as lot_price,
            c.created_at,
            c.updated_at
        ')
        ->orderBy('c.sign_date', 'desc')
        ->limit($limit)
        ->offset($offset)
        ->get();

    echo "✅ Query ejecutado exitosamente\n\n";
    echo "📊 Resultados: {$sales->count()} ventas encontradas\n\n";

    if ($sales->count() > 0) {
        echo "Primeras 3 ventas:\n";
        echo "==================\n\n";
        
        foreach ($sales->take(3) as $index => $sale) {
            echo ($index + 1) . ". Contrato: {$sale->contract_number}\n";
            echo "   Fecha: {$sale->sign_date}\n";
            echo "   Asesor: {$sale->advisor_name}\n";
            echo "   Total: $" . number_format($sale->total_price, 2) . "\n";
            echo "   Inicial: $" . number_format($sale->down_payment, 2) . "\n";
            echo "   Financiamiento: $" . number_format($sale->financing_amount, 2) . "\n";
            echo "   Plazo: {$sale->term_months} meses\n";
            echo "   Cuota mensual: $" . number_format($sale->monthly_payment, 2) . "\n";
            echo "   Lote: {$sale->lot_number} - Manzana: {$sale->manzana_name}\n\n";
        }
    }

    echo "=============================================\n";
    echo "✅ Test completado exitosamente\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}
