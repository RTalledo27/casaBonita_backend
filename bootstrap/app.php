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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Cargar rutas de módulos
            if (file_exists(__DIR__.'/../routes/modules.php')) {
                require __DIR__.'/../routes/modules.php';
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Para Laravel 11, necesitamos crear el middleware TrustProxies
        $middleware->trustProxies(at: [
            '127.0.0.1',
            'localhost',
            '*.vercel.app',
            '*.now.sh'
        ]);
        
        $middleware->trustProxies(headers: 
            Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Add CORS middleware for API routes
        $middleware->api([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\AuditRequestMiddleware::class,
        ]);
        
        // Enable CORS for all routes
        $middleware->web([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Register permission middleware aliases
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        
        // Agregar middleware global para manejar errores de ZipArchive
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
