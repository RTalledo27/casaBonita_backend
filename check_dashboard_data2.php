<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== PAYROLLS COLUMNS ===\n";
$cols = DB::select('DESCRIBE payrolls');
foreach ($cols as $c) { echo "  {$c->Field} ({$c->Type})\n"; }

echo "\n=== PAYROLLS DATA ===\n";
$payrolls = DB::table('payrolls')->limit(3)->get();
foreach ($payrolls as $p) {
    echo json_encode($p, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== PAYROLLS SUMMARY ===\n";
$summary = DB::table('payrolls')
    ->selectRaw('count(*) as cnt, sum(gross_salary) as total_gross, sum(net_salary) as total_net, sum(total_deductions) as total_deductions')
    ->first();
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

echo "\n=== SALES CUTS TABLE ===\n";
try {
    $cnt = DB::table('sales_cuts')->count();
    echo "Count: {$cnt}\n";
    if ($cnt > 0) {
        $sample = DB::table('sales_cuts')->limit(2)->get();
        echo json_encode($sample, JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) { echo "Table not found\n"; }

echo "\n=== COMMISSION-RELATED TABLES ===\n";
$tables = DB::select("SHOW TABLES");
$key = array_keys((array)$tables[0])[0];
foreach ($tables as $table) {
    $name = $table->$key;
    if (str_contains(strtolower($name), 'commission') || str_contains(strtolower($name), 'bonus') || str_contains(strtolower($name), 'attendance') || str_contains(strtolower($name), 'payroll') || str_contains(strtolower($name), 'schedule')) {
        $cnt = DB::table($name)->count();
        echo "  {$name}: {$cnt}\n";
    }
}
