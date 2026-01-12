<?php
/**
 * Script para probar webhooks de Logicware localmente
 * 
 * Uso: php test_webhook.php [tipo_evento]
 * 
 * Ejemplos:
 *   php test_webhook.php sales.process.completed
 *   php test_webhook.php payment.created
 *   php test_webhook.php unit.updated
 */

// Configuración
$webhookUrl = 'https://api.casabonita.pe/api/webhooks/logicware'; // Producción
// $webhookUrl = 'http://127.0.0.1:8000/api/webhooks/logicware'; // Local
$secret = '48bdcc5cc66334177b6eaf2c40e5d121f18002a6c0fbede191e12794a7b82ea9'; // LOGICWARE_WEBHOOK_SECRET del .env

// Payloads de ejemplo para diferentes eventos
$payloads = [
    'sales.process.completed' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'sales.process.completed',
        'eventTimestamp' => date('c'),
        'data' => [
            'ord_correlative' => '202501-' . str_pad(rand(1, 999), 9, '0', STR_PAD_LEFT),
            'ord_total' => 50000.00,
            'client' => [
                'type_document' => 'DNI',
                'document' => '12345678',
                'full_name' => 'JUAN PEREZ PEREZ'
            ],
            'units' => [
                ['unit_number' => 'M-01', 'sub_total' => 50000.00]
            ]
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'test-correlation-' . uniqid()
    ],
    
    'payment.created' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'payment.created',
        'eventTimestamp' => date('c'),
        'data' => [
            'ord_correlative' => '202501-000000001',
            'payment_id' => rand(1000, 9999),
            'amount' => 2500.00,
            'currency' => 'PEN',
            'payment_date' => date('Y-m-d'),
            'installment_number' => 3
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'payment-' . uniqid()
    ],
    
    'schedule.created' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'schedule.created',
        'eventTimestamp' => date('c'),
        'data' => [
            'ord_correlative' => '202501-000000001',
            'schedule_id' => rand(1000, 9999),
            'total_installments' => 24,
            'total_amount' => 50000.00
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'schedule-' . uniqid()
    ],
    
    'separation.process.completed' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'separation.process.completed',
        'eventTimestamp' => date('c'),
        'data' => [
            'ord_correlative' => '202501-000000001',
            'separation_date' => date('Y-m-d'),
            'client' => [
                'type_document' => 'DNI',
                'document' => '87654321',
                'full_name' => 'MARIA RODRIGUEZ GARCIA'
            ]
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'separation-' . uniqid()
    ],
    
    'unit.updated' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'unit.updated',
        'eventTimestamp' => date('c'),
        'data' => [
            'unit_id' => rand(1000, 9999),
            'unit_number' => 'M-' . str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT),
            'status' => 'Vendido',
            'price' => 45000.00
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'unit-' . uniqid()
    ],
    
    'proforma.created' => [
        'messageId' => 'test-' . uniqid(),
        'eventType' => 'proforma.created',
        'eventTimestamp' => date('c'),
        'data' => [
            'ord_correlative' => '202501-' . str_pad(rand(1, 999), 9, '0', STR_PAD_LEFT),
            'ord_total' => 52000.00,
            'client' => [
                'type_document' => 'DNI',
                'document' => '45678912',
                'full_name' => 'CARLOS LOPEZ SANCHEZ'
            ],
            'units' => [
                ['unit_number' => 'M-15', 'sub_total' => 52000.00]
            ]
        ],
        'sourceId' => rand(1000, 9999),
        'correlationId' => 'proforma-' . uniqid()
    ]
];

// Obtener tipo de evento desde argumentos
$eventType = $argv[1] ?? 'sales.process.completed';

if (!isset($payloads[$eventType])) {
    echo "❌ Tipo de evento desconocido: $eventType\n\n";
    echo "Eventos disponibles:\n";
    foreach (array_keys($payloads) as $type) {
        echo "  - $type\n";
    }
    exit(1);
}

$payload = $payloads[$eventType];
$payloadJson = json_encode($payload, JSON_PRETTY_PRINT);

// Calcular firma HMAC-SHA256
$signature = hash_hmac('sha256', json_encode($payload), $secret);

echo "🚀 Enviando webhook de prueba...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "📌 Tipo de evento: $eventType\n";
echo "🆔 Message ID: {$payload['messageId']}\n";
echo "🔗 URL: $webhookUrl\n";
echo "🔐 Firma: sha256=$signature\n\n";
echo "📦 Payload:\n$payloadJson\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Enviar request con cURL
$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Webhook-Signature: sha256=' . $signature,
        'X-LW-Event: ' . $eventType,
        'X-LW-Delivery: test-delivery-' . uniqid(),
        'User-Agent: LogicwareWebhookTest/1.0'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Mostrar resultado
if ($error) {
    echo "❌ ERROR DE CONEXIÓN:\n";
    echo "   $error\n\n";
    exit(1);
}

echo "📨 RESPUESTA DEL SERVIDOR:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Status Code: $httpCode\n\n";

if ($httpCode === 200) {
    echo "✅ WEBHOOK RECIBIDO EXITOSAMENTE\n\n";
    $responseData = json_decode($response, true);
    echo "Respuesta:\n" . json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "🔍 VERIFICAR:\n";
    echo "1. Revisa los logs: tail -f storage/logs/laravel.log\n";
    echo "2. Verifica la tabla: SELECT * FROM webhook_logs WHERE message_id = '{$payload['messageId']}';\n";
    echo "3. Ejecuta el worker: php artisan queue:work --once\n";
    
} elseif ($httpCode === 401) {
    echo "⚠️  FIRMA INVÁLIDA\n";
    echo "Verifica que LOGICWARE_WEBHOOK_SECRET = '$secret'\n\n";
    echo "Respuesta: $response\n";
    
} else {
    echo "❌ ERROR HTTP $httpCode\n\n";
    echo "Respuesta:\n$response\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
