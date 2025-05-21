<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\Auth\AuthController;
use Modules\Security\Http\Controllers\PermissionController;
use Modules\Security\Http\Controllers\RoleController;
use Modules\Security\Http\Controllers\SecurityController;
use Modules\Security\Http\Controllers\UserController;
use Modules\Security\Models\Role;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('securities', SecurityController::class)->names('security');
});


Route::prefix('v1')->group(function () {
    // Login (sin token)
    Route::post('security/login', [AuthController::class, 'login']);

    // Todo lo demás requiere estar autenticado
    Route::middleware('auth:sanctum')
        ->prefix('security')
        ->group(function () {

            // Usuarios
            Route::apiResource('users', UserController::class);
            Route::post('users/{user}/roles',          [UserController::class, 'syncRoles']);
            Route::post('users/{user}/change-password', [UserController::class, 'changePassword']);
            Route::post('users/{user}/toggle-status',  [UserController::class, 'toggleStatus']);

            // Roles
            Route::apiResource('roles', RoleController::class);
            Route::post('roles/{role}/permissions',    [RoleController::class, 'syncPermissions']);

            // Permisos
            Route::apiResource('permissions', PermissionController::class)
                ->only(['index', 'show', 'store']);

            // Logout y perfil
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);
        });
});
