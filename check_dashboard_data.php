<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== COMMISSIONS TABLE ===\n";
$count = DB::table('commissions')->count();
echo "Total records: {$count}\n";

echo "\n=== BONUSES TABLE ===\n";
$count = DB::table('bonuses')->count();
echo "Total records: {$count}\n";
if ($count > 0) {
    $months = DB::table('bonuses')->selectRaw('MONTH(bonus_date) as m, YEAR(bonus_date) as y, count(*) as cnt, sum(bonus_amount) as total')
        ->groupByRaw('y, m')->orderByRaw('y desc, m desc')->limit(5)->get();
    foreach ($months as $row) {
        echo "  {$row->y}-{$row->m}: {$row->cnt} bonuses, total={$row->total}\n";
    }
}

echo "\n=== CONTRACTS (Sales) ===\n";
$count = DB::table('contracts')->count();
echo "Total records: {$count}\n";
if ($count > 0) {
    $months = DB::table('contracts')->selectRaw('MONTH(contract_date) as m, YEAR(contract_date) as y, count(*) as cnt')
        ->groupByRaw('y, m')->orderByRaw('y desc, m desc')->limit(5)->get();
    foreach ($months as $row) {
        echo "  {$row->y}-{$row->m}: {$row->cnt} contracts\n";
    }
}

echo "\n=== PAYROLLS ===\n";
$count = DB::table('payrolls')->count();
echo "Total records: {$count}\n";
if ($count > 0) {
    $months = DB::table('payrolls')->selectRaw('month, year, count(*) as cnt, sum(net_salary) as total_net')
        ->groupByRaw('month, year')->orderByRaw('year desc, month desc')->limit(5)->get();
    foreach ($months as $row) {
        echo "  {$row->year}-{$row->month}: {$row->cnt} payrolls, net_total={$row->total_net}\n";
    }
}

echo "\n=== EMPLOYEES ===\n";
$active = DB::table('employees')->whereNull('deleted_at')->where('employment_status', 'active')->count();
$total = DB::table('employees')->whereNull('deleted_at')->count();
echo "Active: {$active}, Total (not deleted): {$total}\n";

$types = DB::table('employees')->whereNull('deleted_at')->groupBy('employee_type')
    ->selectRaw('employee_type, count(*) as cnt')->get();
foreach ($types as $t) {
    echo "  {$t->employee_type}: {$t->cnt}\n";
}

echo "\n=== TABLES WITH RELEVANT NAMES ===\n";
$tables = DB::select("SHOW TABLES");
$key = array_keys((array)$tables[0])[0];
foreach ($tables as $table) {
    $name = $table->$key;
    if (str_contains(strtolower($name), 'commission') || str_contains(strtolower($name), 'bonus') || str_contains(strtolower($name), 'sale') || str_contains(strtolower($name), 'cut')) {
        $cnt = DB::table($name)->count();
        echo "  {$name}: {$cnt} records\n";
    }
}
