<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

try {
    echo "🔍 Probando autenticación Sanctum...\n\n";
    
    // Buscar usuario admin
    $adminUser = User::where('email', 'admin@casabonita.com')->first();
    
    if (!$adminUser) {
        echo "❌ Usuario admin no encontrado\n";
        exit(1);
    }
    
    echo "✅ Usuario admin encontrado\n";
    
    // Crear un token de prueba
    $token = $adminUser->createToken('test-token', ['*']);
    $plainTextToken = $token->plainTextToken;
    
    echo "🔑 Token creado: " . substr($plainTextToken, 0, 20) . "...\n";
    echo "📋 Token ID: " . $token->accessToken->id . "\n\n";
    
    // Verificar que el token existe en la base de datos
    $tokenRecord = PersonalAccessToken::find($token->accessToken->id);
    if ($tokenRecord) {
        echo "✅ Token guardado en BD\n";
        echo "👤 Usuario ID del token: " . $tokenRecord->tokenable_id . "\n";
        echo "🏷️ Nombre del token: " . $tokenRecord->name . "\n";
        echo "🔐 Habilidades: " . json_encode($tokenRecord->abilities) . "\n\n";
    } else {
        echo "❌ Token no encontrado en BD\n";
    }
    
    // Probar autenticación con el token
    echo "🧪 Probando autenticación...\n";
    
    // Simular una request con el token
    $foundToken = PersonalAccessToken::findToken($plainTextToken);
    if ($foundToken) {
        echo "✅ Token válido encontrado\n";
        echo "👤 Usuario del token: " . $foundToken->tokenable->email . "\n";
        
        // Verificar permisos
        $user = $foundToken->tokenable;
        $hasPermission = $user->hasPermissionTo('hr.commission-verifications.view');
        echo "🔐 Tiene permiso 'hr.commission-verifications.view': " . ($hasPermission ? 'SÍ' : 'NO') . "\n";
        
    } else {
        echo "❌ Token no válido\n";
    }
    
    echo "\n📋 Información para Postman:\n";
    echo "Authorization: Bearer " . $plainTextToken . "\n";
    echo "\n🌐 URL de prueba: http://localhost:8000/api/v1/hr/commission-payment-verifications/requiring-verification\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📚 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✅ Prueba completada\n";