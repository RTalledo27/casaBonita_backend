<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all routes
$routes = Route::getRoutes();

echo "Buscando rutas de reportes...\n\n";

foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'reports') !== false && strpos($uri, 'export') !== false) {
        echo "URI: " . $uri . "\n";
        echo "Method: " . implode('|', $route->methods()) . "\n";
        echo "Action: " . $route->getActionName() . "\n";
        echo "---\n";
    }
}
