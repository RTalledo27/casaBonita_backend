<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Security\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

// Cargar la aplicación Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n=== PRUEBA DE AUTENTICACIÓN SANCTUM ===\n\n";

try {
    // 1. Buscar o crear un usuario de prueba
    echo "1. Buscando usuario de prueba...\n";
    
    $user = User::where('email', 'test@casabonita.com')->first();
    
    if (!$user) {
        echo "   Creando usuario de prueba...\n";
        $user = User::create([
            'username' => 'test_sanctum',
            'first_name' => 'Usuario',
            'last_name' => 'Prueba Sanctum',
            'email' => 'test@casabonita.com',
            'password_hash' => Hash::make('password123'),
        ]);
    }
    
    echo "   Usuario encontrado/creado: {$user->first_name} {$user->last_name} ({$user->email})\n";
    echo "   User ID: {$user->user_id}\n\n";
    
    // 2. Crear un token de acceso personal
    echo "2. Creando token de acceso...\n";
    
    // Eliminar tokens anteriores del usuario de prueba
    $user->tokens()->delete();
    
    $token = $user->createToken('test-sanctum-auth', ['*']);
    $tokenString = $token->plainTextToken;
    
    echo "   Token creado exitosamente\n";
    echo "   Token: {$tokenString}\n\n";
    
    // 3. Crear un archivo CSV de prueba
    echo "3. Creando archivo CSV de prueba...\n";
    
    $csvContent = "ASESOR_NOMBRE,CLIENTE_NOMBRE_COMPLETO,LOTE_NUMERO,FECHA_VENTA\n";
    $csvContent .= "Juan Pérez,María García López,A-001,2024-01-15\n";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'test_import_') . '.csv';
    file_put_contents($tempFile, $csvContent);
    
    echo "   Archivo CSV creado: {$tempFile}\n\n";
    
    // 4. Hacer petición HTTP al endpoint de validación
    echo "4. Probando endpoint de validación...\n";
    
    $url = 'http://localhost:8000/api/v1/sales/import/contracts/validate';
    
    // Preparar datos para la petición
    $postData = [
        'file' => new CURLFile($tempFile, 'text/csv', 'test_import.csv')
    ];
    
    $headers = [
        'Authorization: Bearer ' . $tokenString,
        'Accept: application/json'
    ];
    
    // Configurar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    echo "   Enviando petición a: {$url}\n";
    echo "   Con token: Bearer {$tokenString}\n\n";
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // 5. Analizar respuesta
    echo "5. Analizando respuesta...\n";
    
    if ($curlError) {
        echo "   ❌ Error de cURL: {$curlError}\n";
        throw new Exception("Error de cURL: {$curlError}");
    }
    
    echo "   Código HTTP: {$httpCode}\n";
    
    if ($response) {
        $responseData = json_decode($response, true);
        echo "   Respuesta JSON:\n";
        echo "   " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Verificar si la autenticación funcionó
        if ($httpCode === 200 && isset($responseData['success']) && $responseData['success']) {
            echo "   ✅ ÉXITO: El guard sanctum está funcionando correctamente\n";
            echo "   ✅ Usuario autenticado exitosamente\n";
            echo "   ✅ Endpoint de validación respondió correctamente\n";
        } elseif ($httpCode === 401) {
            echo "   ❌ ERROR: Usuario no autenticado (401)\n";
            echo "   ❌ El guard sanctum NO está funcionando\n";
        } else {
            echo "   ⚠️  ADVERTENCIA: Respuesta inesperada\n";
            echo "   ⚠️  Código: {$httpCode}\n";
        }
    } else {
        echo "   ❌ ERROR: No se recibió respuesta del servidor\n";
    }
    
    // 6. Verificar logs de Laravel
    echo "\n6. Verificando logs de autenticación...\n";
    
    // Buscar en los logs recientes
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -50); // Últimas 50 líneas
        
        $authLogs = array_filter($recentLines, function($line) {
            return strpos($line, 'ContractImportController::') !== false ||
                   strpos($line, 'Usuario obtenido') !== false ||
                   strpos($line, 'user_id') !== false;
        });
        
        if (!empty($authLogs)) {
            echo "   Logs de autenticación encontrados:\n";
            foreach ($authLogs as $log) {
                echo "   📝 {$log}\n";
            }
        } else {
            echo "   ℹ️  No se encontraron logs de autenticación recientes\n";
        }
    }
    
    // 7. Verificar token en base de datos
    echo "\n7. Verificando token en base de datos...\n";
    
    $tokenRecord = PersonalAccessToken::where('tokenable_id', $user->user_id)
        ->where('tokenable_type', get_class($user))
        ->latest()
        ->first();
    
    if ($tokenRecord) {
        echo "   ✅ Token encontrado en base de datos\n";
        echo "   📋 ID del token: {$tokenRecord->id}\n";
        echo "   📋 Nombre: {$tokenRecord->name}\n";
        echo "   📋 Usuario ID: {$tokenRecord->tokenable_id}\n";
        echo "   📋 Creado: {$tokenRecord->created_at}\n";
    } else {
        echo "   ❌ Token NO encontrado en base de datos\n";
    }
    
    // Limpiar archivo temporal
    unlink($tempFile);
    
    echo "\n=== RESUMEN ===\n";
    echo "Usuario de prueba: {$user->first_name} {$user->last_name} (ID: {$user->user_id})\n";
    echo "Token generado: " . (strlen($tokenString) > 20 ? substr($tokenString, 0, 20) . '...' : $tokenString) . "\n";
    echo "Código HTTP: {$httpCode}\n";
    
    if ($httpCode === 200) {
        echo "🎉 RESULTADO: El guard sanctum está funcionando CORRECTAMENTE\n";
    } elseif ($httpCode === 401) {
        echo "❌ RESULTADO: El guard sanctum NO está funcionando\n";
    } else {
        echo "⚠️  RESULTADO: Respuesta inesperada, revisar configuración\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR GENERAL: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
    
    // Limpiar archivo temporal si existe
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
}

echo "\n=== FIN DE LA PRUEBA ===\n\n";