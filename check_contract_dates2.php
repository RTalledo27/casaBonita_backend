<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$cols = DB::select("SHOW COLUMNS FROM contracts WHERE Field LIKE '%date%' OR Field LIKE '%created%' OR Field LIKE '%start%' OR Field LIKE '%sign%'");
foreach ($cols as $c) echo "{$c->Field} | {$c->Type}\n";
