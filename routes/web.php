<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// routes/web.php
Route::get('/health', function () {
    try {
        $checks = [
            'php_version' => PHP_VERSION,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'mbstring' => extension_loaded('mbstring'),
                'xml' => extension_loaded('xml'),
                'tokenizer' => extension_loaded('tokenizer'),
                'json' => extension_loaded('json'),
            ],
            'storage_writable' => is_writable('/tmp'),
            'vendor_exists' => file_exists('vendor/autoload.php'),
            'app_exists' => file_exists('bootstrap/app.php'),
        ];

        return response()->json([
            'status' => 'healthy',
            'checks' => $checks
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});
