<?php

require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Http\Request;
use Modules\Reports\Http\Controllers\ProjectedReportController;

echo "🧪 Testing Chart Data Structure\n";
echo "===========================================================\n\n";

$controller = new ProjectedReportController();

// Test Revenue Chart
echo "1️⃣ Revenue Chart Data Structure\n";
echo "-----------------------------------------------------------\n";
$request = new Request(['year' => 2025, 'months_ahead' => 12]);
$response = $controller->getRevenueProjectionChart($request);
$data = json_decode($response->getContent(), true);
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Test Sales Chart
echo "2️⃣ Sales Chart Data Structure\n";
echo "-----------------------------------------------------------\n";
$request = new Request(['year' => 2025]);
$response = $controller->getSalesProjectionChart($request);
$data = json_decode($response->getContent(), true);
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Test Cash Flow Chart
echo "3️⃣ Cash Flow Chart Data Structure\n";
echo "-----------------------------------------------------------\n";
$request = new Request(['year' => 2025, 'months_ahead' => 12]);
$response = $controller->getCashFlowChart($request);
$data = json_decode($response->getContent(), true);
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

echo "===========================================================\n";
echo "✅ Chart structure testing complete!\n";
