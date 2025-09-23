<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HumanResources\app\Http\Controllers\CommissionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/debug-commission/find-testable', [CommissionController::class, 'debugTestPayPart']);
Route::post('/debug-commission/{commission_id}/debug-pay-part', [CommissionController::class, 'debugPayPart']);
Route::post('/debug-commission/{commission_id}/set-approved', [CommissionController::class, 'debugSetApproved']);
