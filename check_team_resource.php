<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Transformers\TeamResource;
$t = Team::with(['employees','leader','office'])->find(1);
$resource = new TeamResource($t);
echo json_encode($resource->resolve(), JSON_PRETTY_PRINT);
