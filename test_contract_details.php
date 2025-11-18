<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST: Contract Details API Response ===\n\n";

// Obtener un contrato reciente de Logicware (o cualquiera si no hay de Logicware)
$contract = \Modules\Sales\Models\Contract::where('source', 'logicware')
    ->with(['lot.manzana', 'client', 'advisor.user'])
    ->first();

if (!$contract) {
    echo "⚠️ No hay contratos de Logicware, buscando cualquier contrato...\n";
    $contract = \Modules\Sales\Models\Contract::with(['lot.manzana', 'client', 'advisor.user'])
        ->first();
}

if (!$contract) {
    echo "❌ No se encontraron contratos de Logicware\n";
    exit;
}

echo "📋 Contrato: {$contract->contract_number}\n";
echo "   ID: {$contract->contract_id}\n\n";

echo "🏠 Información del Lote:\n";
echo "   - Lote: " . ($contract->getLotName() ?? 'N/A') . "\n";
echo "   - Manzana: " . ($contract->getManzanaName() ?? 'No especificado') . "\n";
echo "   - Área: " . ($contract->getArea() ? $contract->getArea() . ' m²' : 'No especificado') . "\n\n";

echo "👤 Cliente:\n";
echo "   - Nombre: " . ($contract->getClientName() ?? 'N/D') . "\n";
if ($contract->client) {
    echo "   - Email: " . ($contract->client->email ?? 'N/D') . "\n";
    echo "   - Teléfono: " . ($contract->client->primary_phone ?? 'N/D') . "\n";
}
echo "\n";

echo "👔 Asesor:\n";
$advisor = $contract->getAdvisor();
if ($advisor && $advisor->user) {
    echo "   - Nombre: " . ($advisor->user->first_name ?? '') . ' ' . ($advisor->user->last_name ?? '') . "\n";
    echo "   - Email: " . ($advisor->user->email ?? 'N/D') . "\n";
} else {
    echo "   - Sin asesor\n";
}
echo "\n";

echo "💰 Información Financiera:\n";
echo "   - Precio Total: S/ " . number_format($contract->total_price, 2) . "\n";
echo "   - Cuota Inicial: S/ " . number_format($contract->down_payment, 2) . "\n";
echo "   - Financiamiento: S/ " . number_format($contract->financing_amount, 2) . "\n";
echo "   - Plazo: {$contract->term_months} meses\n";
echo "   - Cuota Mensual: S/ " . number_format($contract->monthly_payment, 2) . "\n\n";

// Simular respuesta del Resource
echo "📤 Respuesta API (ContractResource):\n";
$resource = new \Modules\Sales\Http\Resources\ContractResource($contract);
$response = $resource->toArray(request());

echo json_encode([
    'lot_name' => $response['lot_name'] ?? 'N/A',
    'manzana_name' => $response['manzana_name'] ?? 'No especificado',
    'area_m2' => $response['area_m2'] ?? null,
    'client_name' => $response['client_name'] ?? 'N/D',
    'advisor_name' => $response['advisor_name'] ?? 'Sin asesor'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n✅ Test completado\n";
