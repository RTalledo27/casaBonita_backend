<?php

use Illuminate\Support\Facades\Route;

// Registrar rutas de módulos explícitamente para Vercel
$modules = [
    'Security',
    'CRM',
    'Inventory',
    'Sales',
    'Accounting',
    'Integrations',
    'ServiceDesk',
    'Audit',
    'Finance',
    'Collections',
    'HumanResources'
];

foreach ($modules as $moduleName) {
    $moduleApiRoutes = base_path("Modules/{$moduleName}/routes/api.php");
    if (file_exists($moduleApiRoutes)) {
        Route::middleware('api')
            ->prefix('api')
            ->group($moduleApiRoutes);
    }
}