<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Casa Bonita API is running',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'Casa Bonita Backend',
        'timestamp' => now()->toISOString()
    ]);
});

