<?php

/**
 * Script de prueba para validar la integración completa con Infobip
 * 
 * Este script prueba todos los endpoints:
 * - Email
 * - SMS
 * - WhatsApp (texto simple)
 * - WhatsApp (template)
 * 
 * Uso: php test_infobip_integration.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==============================================\n";
echo "  TEST DE INTEGRACIÓN INFOBIP - Casa Bonita  \n";
echo "==============================================\n\n";

// Colores para la terminal
$GREEN = "\033[32m";
$RED = "\033[31m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$RESET = "\033[0m";

// Configuración de prueba
$testEmail = readline("Ingresa el email de prueba (Enter para usar romaim.talledo@casabonita.pe): ") ?: "romaim.talledo@casabonita.pe";
$testPhone = readline("Ingresa el teléfono de prueba con código país +51 (Enter para saltar SMS/WhatsApp): ");

// Verificar configuración
echo "\n${BLUE}[1/5] Verificando configuración de Infobip...${RESET}\n";
$config = [
    'Base URL' => config('infobip.base_url'),
    'API Key' => config('infobip.api_key') ? substr(config('infobip.api_key'), 0, 20) . '...' : 'NO CONFIGURADO',
    'Email Sender' => config('infobip.email_sender'),
    'SMS Sender' => config('infobip.sms_sender') ?: 'NO CONFIGURADO',
    'WhatsApp Sender' => config('infobip.whatsapp_sender') ?: 'NO CONFIGURADO',
    'Email via API' => config('infobip.email_via_api') ? 'SI' : 'NO',
    'Canales activos' => is_array(config('infobip.channels')) ? implode(', ', config('infobip.channels')) : config('infobip.channels'),
];

foreach ($config as $key => $value) {
    echo "  - {$key}: {$value}\n";
}

if (!config('infobip.api_key')) {
    echo "\n${RED}ERROR: API Key de Infobip no configurado en .env${RESET}\n";
    exit(1);
}

// Test 1: Email
echo "\n${BLUE}[2/5] Probando envío de Email...${RESET}\n";
try {
    $infobipClient = app(\App\Services\InfobipClient::class);
    
    $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #ecf0f1; padding: 10px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Casa Bonita Residencial</h1>
            </div>
            <div class='content'>
                <h2>Test de Integración Infobip</h2>
                <p>Este es un correo de prueba enviado desde el sistema de Casa Bonita.</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p>Si recibes este correo, la integración con Infobip está funcionando correctamente.</p>
            </div>
            <div class='footer'>
                <p>Casa Bonita Residencial - Sistema ERP</p>
                <p>Este es un correo automático, por favor no responder.</p>
            </div>
        </body>
        </html>
    ";
    
    $result = $infobipClient->sendEmail(
        $testEmail,
        'Test de Integración Infobip - Casa Bonita',
        $html
    );
    
    if ($result['ok']) {
        echo "  ${GREEN}✓ Email enviado exitosamente${RESET}\n";
        echo "  Status: " . $result['status'] . "\n";
        if (isset($result['body']['messages'][0]['messageId'])) {
            echo "  Message ID: " . $result['body']['messages'][0]['messageId'] . "\n";
        }
    } else {
        echo "  ${RED}✗ Error al enviar email${RESET}\n";
        echo "  Status: " . $result['status'] . "\n";
        echo "  Response: " . json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) {
    echo "  ${RED}✗ Excepción: " . $e->getMessage() . "${RESET}\n";
}

// Test 2: SMS
if ($testPhone) {
    echo "\n${BLUE}[3/5] Probando envío de SMS...${RESET}\n";
    
    if (!config('infobip.sms_sender')) {
        echo "  ${YELLOW}⚠ SMS Sender no configurado, saltando prueba${RESET}\n";
    } else {
        try {
            $result = $infobipClient->sendSms(
                $testPhone,
                'Casa Bonita: Test de integración Infobip. Si recibes este mensaje, el sistema está funcionando correctamente.'
            );
            
            if ($result['ok']) {
                echo "  ${GREEN}✓ SMS enviado exitosamente${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                if (isset($result['body']['messages'][0]['messageId'])) {
                    echo "  Message ID: " . $result['body']['messages'][0]['messageId'] . "\n";
                }
            } else {
                echo "  ${RED}✗ Error al enviar SMS${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                echo "  Response: " . json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
            }
        } catch (\Exception $e) {
            echo "  ${RED}✗ Excepción: " . $e->getMessage() . "${RESET}\n";
        }
    }
    
    // Test 3: WhatsApp texto simple
    echo "\n${BLUE}[4/5] Probando envío de WhatsApp (texto)...${RESET}\n";
    
    if (!config('infobip.whatsapp_sender')) {
        echo "  ${YELLOW}⚠ WhatsApp Sender no configurado, saltando prueba${RESET}\n";
    } else {
        try {
            $result = $infobipClient->sendWhatsappText(
                $testPhone,
                '🏡 *Casa Bonita Residencial* 🏡\n\nTest de integración Infobip.\nSi recibes este mensaje, el sistema está funcionando correctamente.\n\nFecha: ' . date('d/m/Y H:i:s')
            );
            
            if ($result['ok']) {
                echo "  ${GREEN}✓ WhatsApp enviado exitosamente${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                if (isset($result['body']['messageId'])) {
                    echo "  Message ID: " . $result['body']['messageId'] . "\n";
                }
            } else {
                echo "  ${RED}✗ Error al enviar WhatsApp${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                echo "  Response: " . json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
            }
        } catch (\Exception $e) {
            echo "  ${RED}✗ Excepción: " . $e->getMessage() . "${RESET}\n";
        }
    }
    
    // Test 4: WhatsApp template (opcional)
    echo "\n${BLUE}[5/5] Probando WhatsApp Template (opcional)...${RESET}\n";
    
    $templateName = config('infobip.wa_template_name');
    if (!$templateName) {
        echo "  ${YELLOW}⚠ No hay template configurado, saltando prueba${RESET}\n";
    } else {
        try {
            $result = $infobipClient->sendWhatsappTemplate(
                $testPhone,
                $templateName,
                ['Casa Bonita', date('d/m/Y')],
                config('infobip.wa_template_language', 'es'),
                config('infobip.wa_template_namespace')
            );
            
            if ($result['ok']) {
                echo "  ${GREEN}✓ WhatsApp Template enviado exitosamente${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                if (isset($result['body']['messageId'])) {
                    echo "  Message ID: " . $result['body']['messageId'] . "\n";
                }
            } else {
                echo "  ${RED}✗ Error al enviar WhatsApp Template${RESET}\n";
                echo "  Status: " . $result['status'] . "\n";
                echo "  Response: " . json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
            }
        } catch (\Exception $e) {
            echo "  ${RED}✗ Excepción: " . $e->getMessage() . "${RESET}\n";
        }
    }
} else {
    echo "\n${YELLOW}[3/5] SMS - Saltado (no se proporcionó teléfono)${RESET}\n";
    echo "${YELLOW}[4/5] WhatsApp - Saltado (no se proporcionó teléfono)${RESET}\n";
    echo "${YELLOW}[5/5] WhatsApp Template - Saltado (no se proporcionó teléfono)${RESET}\n";
}

// Resumen
echo "\n==============================================\n";
echo "  PRUEBAS COMPLETADAS\n";
echo "==============================================\n";
echo "\n${BLUE}Revisa los logs en storage/logs/laravel.log para más detalles${RESET}\n";
echo "${BLUE}Revisa tu email ($testEmail) para verificar la recepción${RESET}\n";
if ($testPhone) {
    echo "${BLUE}Revisa tu teléfono ($testPhone) para verificar SMS/WhatsApp${RESET}\n";
}
echo "\n";
