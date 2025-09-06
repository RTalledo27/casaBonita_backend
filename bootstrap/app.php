<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Nwidart\Modules\LaravelModulesServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Cargar rutas de todos los módulos activos
            if (file_exists(__DIR__.'/../routes/modules.php')) {
                require __DIR__.'/../routes/modules.php';
            }
            
            // Cargar rutas de cada módulo individualmente
            try {
                $modulesPath = __DIR__.'/../Modules';
                if (is_dir($modulesPath)) {
                    $modules = scandir($modulesPath);
                    foreach ($modules as $module) {
                        if ($module === '.' || $module === '..') continue;
                        
                        $routePath = "{$modulesPath}/{$module}/Routes/web.php";
                        if (file_exists($routePath)) {
                            require $routePath;
                        }
                        
                        $apiRoutePath = "{$modulesPath}/{$module}/Routes/api.php";
                        if (file_exists($apiRoutePath)) {
                            require $apiRoutePath;
                        }
                    }
                }
            } catch (Exception $e) {
                // Silenciar errores durante el routing
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register permission middleware aliases
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        
        // Middleware global para módulos
        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();