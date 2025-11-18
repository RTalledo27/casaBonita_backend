<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$correlative = '202511-000000596'; // Ejemplo de la data que compartiste

$baseUrl = config('services.logicware.api_url', 'https://api.logicware.app');
$clientId = config('services.logicware.client_id');
$clientSecret = config('services.logicware.client_secret');
$subdomain = 'casabonita';

echo "🔐 Obteniendo token de acceso...\n";

try {
    // Obtener token
    $tokenResponse = \Illuminate\Support\Facades\Http::post("{$baseUrl}/oauth/token", [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    if (!$tokenResponse->successful()) {
        die("❌ Error al obtener token: " . $tokenResponse->body() . "\n");
    }

    $accessToken = $tokenResponse->json('access_token');
    echo "✅ Token obtenido\n\n";

    // Consultar cronograma de pagos
    echo "📅 Consultando cronograma de pagos para: {$correlative}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $scheduleResponse = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => "Bearer {$accessToken}",
        'X-Subdomain' => $subdomain,
        'Accept' => 'application/json',
    ])->get("{$baseUrl}/external/payment-schedules/{$correlative}");

    if (!$scheduleResponse->successful()) {
        echo "❌ Error HTTP {$scheduleResponse->status()}\n";
        echo $scheduleResponse->body() . "\n";
        exit(1);
    }

    $data = $scheduleResponse->json();

    echo "✅ Respuesta exitosa\n\n";
    echo "📦 ESTRUCTURA COMPLETA:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Analizar la estructura
    if (isset($data['data']) && is_array($data['data'])) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 ANÁLISIS DEL CRONOGRAMA:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $schedules = $data['data'];
        $totalCuotas = count($schedules);
        $cuotasPagadas = 0;
        $cuotasPendientes = 0;
        $totalPagado = 0;
        $totalPendiente = 0;

        echo "Total de cuotas: {$totalCuotas}\n\n";

        foreach ($schedules as $index => $schedule) {
            $numero = $index + 1;
            $monto = $schedule['amount'] ?? 0;
            $fechaVencimiento = $schedule['dueDate'] ?? 'N/A';
            $estado = $schedule['status'] ?? 'unknown';
            $pagado = $schedule['paid'] ?? 0;
            $saldo = $schedule['balance'] ?? $monto;
            
            $isPagada = $pagado >= $monto || $estado === 'paid' || $saldo == 0;
            
            if ($isPagada) {
                $cuotasPagadas++;
                $totalPagado += $monto;
                $estadoIcon = '✅';
            } else {
                $cuotasPendientes++;
                $totalPendiente += $saldo;
                $estadoIcon = '⏳';
            }

            echo "Cuota #{$numero} {$estadoIcon}\n";
            echo "  Monto: S/ " . number_format($monto, 2) . "\n";
            echo "  Vencimiento: {$fechaVencimiento}\n";
            echo "  Estado: {$estado}\n";
            echo "  Pagado: S/ " . number_format($pagado, 2) . "\n";
            echo "  Saldo: S/ " . number_format($saldo, 2) . "\n";
            
            // Mostrar campos disponibles
            echo "  Campos disponibles: " . implode(', ', array_keys($schedule)) . "\n";
            echo "\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📈 RESUMEN:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ Cuotas pagadas: {$cuotasPagadas}\n";
        echo "⏳ Cuotas pendientes: {$cuotasPendientes}\n";
        echo "💰 Total pagado: S/ " . number_format($totalPagado, 2) . "\n";
        echo "💸 Total pendiente: S/ " . number_format($totalPendiente, 2) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
