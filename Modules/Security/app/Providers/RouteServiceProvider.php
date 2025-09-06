<?php

namespace Modules\Security\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            // API del módulo: /api/v1/security/...
            Route::middleware('api')
                ->prefix('api/v1/security')
                ->group(module_path('Security', 'routes/api.php'));

            // Rutas web del módulo (opcional)
            Route::middleware('web')
                ->group(module_path('Security', 'routes/web.php'));
        });
    }
}