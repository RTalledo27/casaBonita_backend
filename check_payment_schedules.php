<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get payment_schedules structure
$columns = DB::select('DESCRIBE payment_schedules');

echo "Estructura de payment_schedules:\n\n";
foreach ($columns as $column) {
    echo "- {$column->Field} ({$column->Type})\n";
}

// Get sample data
echo "\n\nEjemplo de datos (primeros 5 registros):\n\n";
$samples = DB::table('payment_schedules')
    ->join('contracts', 'payment_schedules.contract_id', '=', 'contracts.contract_id')
    ->select(
        'payment_schedules.schedule_id',
        'payment_schedules.contract_id',
        'contracts.client_name',
        'payment_schedules.installment_number',
        'payment_schedules.due_date',
        'payment_schedules.amount',
        'payment_schedules.status'
    )
    ->where('payment_schedules.status', '!=', 'paid')
    ->orderBy('payment_schedules.due_date')
    ->limit(5)
    ->get();

foreach ($samples as $sample) {
    echo "ID: {$sample->schedule_id} | Contrato: {$sample->contract_id} | ";
    echo "Cliente: {$sample->client_name} | ";
    echo "Cuota #{$sample->installment_number} | ";
    echo "Vence: {$sample->due_date} | ";
    echo "Monto: S/ {$sample->amount} | ";
    echo "Estado: {$sample->status}\n";
}

echo "\n\nTotal de cuotas pendientes: " . DB::table('payment_schedules')
    ->whereIn('status', ['pending', 'overdue'])
    ->count() . "\n";
