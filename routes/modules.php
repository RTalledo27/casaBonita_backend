<?php

// routes/modules.php
// Cargar rutas de módulos nwidart automáticamente

use Illuminate\Support\Facades\Route;

try {
    $modulesPath = base_path('Modules');
    if (is_dir($modulesPath)) {
        $modules = scandir($modulesPath);
        
        foreach ($modules as $module) {
            if ($module === '.' || $module === '..') continue;
            
            // Cargar rutas web de módulos
            $webRoutePath = "{$modulesPath}/{$module}/Routes/web.php";
            if (file_exists($webRoutePath)) {
                Route::middleware('web')
                    ->group(function () use ($webRoutePath) {
                        require $webRoutePath;
                    });
            }
            
            // Cargar rutas API de módulos
            $apiRoutePath = "{$modulesPath}/{$module}/Routes/api.php";
            if (file_exists($apiRoutePath)) {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(function () use ($apiRoutePath) {
                        require $apiRoutePath;
                    });
            }
        }
    }
} catch (Exception $e) {
    // Log error pero continuar
    if (app()->has('log')) {
        app('log')->error('Error loading module routes: ' . $e->getMessage());
    }
}


/*use Illuminate\Support\Facades\Route;

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
}*/