<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

use Nwidart\Modules\LaravelModulesServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        LaravelModulesServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Registrar rutas de módulos automáticamente
            if (class_exists('\Nwidart\Modules\Facades\Module')) {
                foreach (\Nwidart\Modules\Facades\Module::allEnabled() as $module) {
                    $moduleApiRoutes = $module->getPath() . '/routes/api.php';
                    if (file_exists($moduleApiRoutes)) {
                        \Illuminate\Support\Facades\Route::middleware('api')
                            ->prefix('api')
                            ->group($moduleApiRoutes);
                    }
                }
            } else {
                // Fallback para entornos serverless como Vercel
                $modulesPath = base_path('Modules');
                if (is_dir($modulesPath)) {
                    $modules = ['Security', 'CRM', 'Inventory', 'Sales', 'Accounting', 'Integrations', 'ServiceDesk', 'Audit', 'Finance', 'Collections', 'HumanResources'];
                    foreach ($modules as $moduleName) {
                        $moduleApiRoutes = $modulesPath . '/' . $moduleName . '/routes/api.php';
                        if (file_exists($moduleApiRoutes)) {
                            \Illuminate\Support\Facades\Route::middleware('api')
                                ->prefix('api')
                                ->group($moduleApiRoutes);
                        }
                    }
                }
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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
