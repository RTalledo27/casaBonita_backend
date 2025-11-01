<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n🔍 Verificando datos para el Dashboard de Reportes\n";
echo "=================================================\n\n";

// 1. Check contracts data
echo "1️⃣ CONTRATOS VIGENTES:\n";
$contracts = DB::table('contracts')
    ->where('status', 'vigente')
    ->whereBetween('sign_date', ['2024-01-01', '2025-12-31'])
    ->selectRaw('
        COUNT(*) as total_sales,
        SUM(total_price) as total_revenue,
        AVG(total_price) as average_sale,
        MIN(sign_date) as first_sale,
        MAX(sign_date) as last_sale
    ')
    ->first();

if ($contracts) {
    echo "   • Total ventas: {$contracts->total_sales}\n";
    echo "   • Ingresos totales: $" . number_format($contracts->total_revenue, 2) . "\n";
    echo "   • Promedio por venta: $" . number_format($contracts->average_sale, 2) . "\n";
    echo "   • Primera venta: {$contracts->first_sale}\n";
    echo "   • Última venta: {$contracts->last_sale}\n";
}

// 2. Check sales by month
echo "\n2️⃣ VENTAS POR MES (2024-2025):\n";
$monthlySales = DB::table('contracts')
    ->where('status', 'vigente')
    ->whereBetween('sign_date', ['2024-01-01', '2025-12-31'])
    ->selectRaw("
        DATE_FORMAT(sign_date, '%Y-%m') as period,
        COUNT(*) as sales_count,
        SUM(total_price) as total_revenue
    ")
    ->groupBy('period')
    ->orderBy('period')
    ->get();

if ($monthlySales->count() > 0) {
    foreach ($monthlySales as $month) {
        echo "   • {$month->period}: {$month->sales_count} ventas - $" . number_format($month->total_revenue, 2) . "\n";
    }
} else {
    echo "   ⚠️ No hay ventas por mes\n";
}

// 3. Check top performers
echo "\n3️⃣ TOP 5 ASESORES:\n";
$topPerformers = DB::table('contracts as c')
    ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
    ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
    ->where('c.status', 'vigente')
    ->whereBetween('c.sign_date', ['2024-01-01', '2025-12-31'])
    ->selectRaw('
        e.employee_id,
        CONCAT(u.first_name, " ", u.last_name) as employee_name,
        COUNT(*) as sales_count,
        SUM(c.total_price) as total_revenue,
        MAX(c.sign_date) as latest_sale_date
    ')
    ->groupBy('e.employee_id', 'u.first_name', 'u.last_name')
    ->orderBy('total_revenue', 'desc')
    ->limit(5)
    ->get();

if ($topPerformers->count() > 0) {
    foreach ($topPerformers as $performer) {
        echo "   • {$performer->employee_name}: {$performer->sales_count} ventas - $" . number_format($performer->total_revenue, 2) . " - Última: {$performer->latest_sale_date}\n";
    }
} else {
    echo "   ⚠️ No hay asesores con ventas\n";
}

// 4. Check payment schedules
echo "\n4️⃣ CRONOGRAMAS DE PAGO:\n";
$payments = DB::table('payment_schedules')
    ->selectRaw('
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = "pendiente" THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = "vencida" THEN amount ELSE 0 END) as total_overdue,
        SUM(CASE WHEN status = "pagado" THEN amount ELSE 0 END) as total_paid
    ')
    ->first();

if ($payments) {
    echo "   • Total pagos programados: {$payments->total_payments}\n";
    echo "   • Pagos pendientes: $" . number_format($payments->total_pending, 2) . "\n";
    echo "   • Pagos vencidos: $" . number_format($payments->total_overdue, 2) . "\n";
    echo "   • Pagos realizados: $" . number_format($payments->total_paid, 2) . "\n";
    
    $totalExpected = $payments->total_pending + $payments->total_overdue + $payments->total_paid;
    if ($totalExpected > 0) {
        $efficiency = ($payments->total_paid / $totalExpected) * 100;
        echo "   • Eficiencia de cobranza: " . number_format($efficiency, 1) . "%\n";
    }
}

// 5. Check if tables exist
echo "\n5️⃣ VERIFICACIÓN DE TABLAS:\n";
$tables = ['contracts', 'employees', 'users', 'payment_schedules'];
foreach ($tables as $table) {
    try {
        $count = DB::table($table)->count();
        echo "   • Tabla '{$table}': ✅ {$count} registros\n";
    } catch (\Exception $e) {
        echo "   • Tabla '{$table}': ❌ No existe o no accesible\n";
    }
}

echo "\n=================================================\n";
echo "✅ Verificación completada\n\n";
