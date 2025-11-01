<?php

echo "🧪 Testing Projected Reports API\n";
echo "===========================================================\n\n";

$baseUrl = 'http://localhost:8000';

// Test 1: Get all projections
echo "1️⃣ Testing GET /v1/reports/projected\n";
echo "-----------------------------------------------------------\n";
$url = "$baseUrl/v1/reports/projected?year=2025&scenario=realistic";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Success!\n\n";
    echo "📊 Projections Found: " . count($data['data']) . "\n\n";
    
    foreach ($data['data'] as $projection) {
        echo "  • {$projection['name']}\n";
        echo "    Type: {$projection['type']}\n";
        echo "    Value: $" . number_format($projection['projectedValue'], 2) . "\n";
        echo "    Confidence: {$projection['confidence']}%\n";
        echo "    Variation: {$projection['variation']}%\n\n";
    }
} else {
    echo "❌ Error!\n";
    echo "Response: $response\n\n";
}

// Test 2: Get key metrics
echo "\n2️⃣ Testing GET /v1/reports/projected/metrics\n";
echo "-----------------------------------------------------------\n";
$url = "$baseUrl/v1/reports/projected/metrics?year=2025&scenario=realistic";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Success!\n\n";
    echo "📊 Key Metrics:\n";
    echo "  • Projected Revenue: $" . number_format($data['data']['projected_revenue'], 2) . "\n";
    echo "  • Projected Sales: " . $data['data']['projected_sales'] . "\n";
    echo "  • Projected Cash Flow: $" . number_format($data['data']['projected_cash_flow'], 2) . "\n";
    echo "  • Projected ROI: " . $data['data']['projected_roi'] . "%\n";
    echo "  • Scenario: " . $data['data']['scenario'] . "\n\n";
} else {
    echo "❌ Error!\n";
    echo "Response: $response\n\n";
}

// Test 3: Get revenue projection chart
echo "\n3️⃣ Testing GET /v1/reports/projected/charts/revenue\n";
echo "-----------------------------------------------------------\n";
$url = "$baseUrl/v1/reports/projected/charts/revenue?year=2025&months_ahead=12";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Success!\n\n";
    echo "📈 Chart Data:\n";
    echo "  • Labels: " . implode(', ', $data['data']['labels']) . "\n";
    echo "  • Scenarios: " . count($data['data']['datasets']) . "\n";
    foreach ($data['data']['datasets'] as $dataset) {
        echo "    - {$dataset['label']}: " . count($dataset['data']) . " data points\n";
    }
    echo "\n";
} else {
    echo "❌ Error!\n";
    echo "Response: $response\n\n";
}

echo "===========================================================\n";
echo "✅ Projected Reports API testing complete!\n";
