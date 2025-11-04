<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "🚀 Probando envío de email con Gmail...\n\n";

try {
    Mail::raw('✅ Test desde Casa Bonita - Sistema de email funcionando correctamente!', function($message) {
        $message->to('romaim.talledo@casabonita.pe')
                ->subject('Test Email - Casa Bonita Residencial');
    });
    
    echo "✅ EMAIL ENVIADO EXITOSAMENTE!\n\n";
    echo "📧 Revisa tu bandeja de entrada: romaim.talledo@casabonita.pe\n";
    echo "📁 Si no lo ves, revisa la carpeta de SPAM\n\n";
    echo "🎉 La configuración de Gmail está funcionando perfectamente!\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR al enviar email:\n";
    echo $e->getMessage() . "\n\n";
    echo "💡 Verifica:\n";
    echo "   1. La contraseña de aplicación esté correcta\n";
    echo "   2. Verificación en 2 pasos esté activa\n";
    echo "   3. El email sea válido\n";
}
