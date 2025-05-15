<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\RoleController;
use Modules\Security\Http\Controllers\SecurityController;
use Modules\Security\Http\Controllers\UserController;
use Modules\Security\Models\Role;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('securities', SecurityController::class)->names('security');
});

Route::prefix('v1')->group(function () {
    Route::prefix('security')->group(function () {
       Route::apiResource('roles', RoleController::class);
       Route::apiResource('users', UserController::class);
    });
});
