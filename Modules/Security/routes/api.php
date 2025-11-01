<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\Auth\AuthController;
use Modules\Security\Http\Controllers\NotificationController;
use Modules\Security\Http\Controllers\PermissionController;
use Modules\Security\Http\Controllers\RoleController;
use Modules\Security\Http\Controllers\SecurityController;
use Modules\Security\Http\Controllers\UserController;
use Modules\Security\Models\Role;

Route::get('ping', fn () => ['ok' => true]); // prueba rápida: GET /api/v1/security/ping


Route::prefix('v1')->group(function () {
    // Login (sin token)
    Route::post('security/login', [AuthController::class, 'login'])->name('login');
    Route::middleware('auth:sanctum')
        ->prefix('security')
        ->group(function () {

            // Rutas que NO requieren cambio de contraseña (permitidas siempre)
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);

            // Rutas que SÍ requieren cambio de contraseña obligatorio
            Route::middleware('check.password.change')->group(function () {
                // Usuarios
                Route::apiResource('users', UserController::class);
                Route::post('users/{user}/roles',          [UserController::class, 'syncRoles']);
                Route::post('users/{user}/change-password', [UserController::class, 'changePassword']);
                Route::post('users/{user}/toggle-status',  [UserController::class, 'toggleStatus']);

                // Roles
                Route::apiResource('roles', RoleController::class);
                Route::post('roles/{role}/permissions',    [RoleController::class, 'syncPermissions']);

                // Permisos
                Route::apiResource('permissions', PermissionController::class);
                
                // Rutas de notificaciones
                Route::get('notifications', [NotificationController::class, 'index']);
                Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
                Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
                Route::patch('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
            });
        });

    // Ruta de securities también requiere cambio de contraseña
    Route::middleware(['auth:sanctum', 'check.password.change'])->group(function () {
        Route::apiResource('securities', SecurityController::class)->names('securities');
    });
});
