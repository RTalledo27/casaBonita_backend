<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Modules\HumanResources\Models\Team;
$t = Team::with('employees')->find(1);
echo count($t->employees) . ' employees' . PHP_EOL;
$e = $t->employees->first();
echo json_encode([
  'employee_id' => $e->employee_id,
  'first_name' => $e->first_name,
  'last_name' => $e->last_name,
  'full_name' => $e->full_name,
  'email' => $e->email,
  'employee_type' => $e->employee_type,
  'status' => $e->status,
  'employment_status' => $e->employment_status,
], JSON_PRETTY_PRINT);
