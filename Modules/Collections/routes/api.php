<?php

use Illuminate\Support\Facades\Route;
use Modules\Collections\Http\Controllers\CollectionsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('collections', CollectionsController::class)->names('collections');
});
