<?php

// Test the revenue projection endpoint via HTTP
$url = 'http://localhost:8000/v1/reports/projections/revenue?quarters_ahead=4&years_back=2';

echo "🚀 Testing Revenue Projection API Endpoint\n";
echo str_repeat("=", 60) . "\n\n";
echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo str_repeat("-", 60) . "\n\n";

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if ($data && $data['success']) {
        echo "✅ Success!\n\n";
        
        $projection = $data['data'];
        
        echo "📊 Historical Data:\n";
        foreach ($projection['historical_data'] as $quarter) {
            echo sprintf("  • %s: %d sales, $%s\n", 
                $quarter['quarter_label'],
                $quarter['sales_count'],
                number_format($quarter['total_revenue'], 2)
            );
        }
        
        echo "\n🎯 Current Quarter:\n";
        $current = $projection['current_quarter'];
        echo sprintf("  • %s: $%s actual\n", 
            $current['quarter_label'],
            number_format($current['actual_revenue'], 2)
        );
        echo sprintf("  • Progress: %.1f%% (%d/%d days)\n",
            $current['progress_percentage'],
            $current['days_elapsed'],
            $current['days_in_quarter']
        );
        echo sprintf("  • Projected End: $%s\n",
            number_format($current['projected_quarter_end'], 2)
        );
        
        echo "\n🔮 Future Projections:\n";
        foreach ($projection['projections'] as $proj) {
            echo sprintf("  • %s: $%s (confidence: %.1f%%)\n",
                $proj['quarter_label'],
                number_format($proj['projected_revenue'], 2),
                $proj['confidence'] * 100
            );
        }
        
        echo "\n📈 Growth Analysis:\n";
        echo sprintf("  • Average Growth Rate: %.2f%%\n", 
            $projection['growth_analysis']['average_growth_rate']
        );
        echo sprintf("  • Trend: %s\n", $projection['summary']['trend']);
        
        echo "\n🌦️ Seasonal Factors:\n";
        foreach ($projection['seasonal_factors'] as $q => $factor) {
            $indicator = $factor > 1.1 ? "🔥" : ($factor < 0.9 ? "❄️" : "➡️");
            echo sprintf("  %s %s: %.2fx\n", $indicator, $q, $factor);
        }
        
        echo "\n📊 Regression Quality:\n";
        echo sprintf("  • R²: %.4f\n", $projection['regression_quality']['r_squared']);
        echo sprintf("  • Interpretation: %s\n", $projection['regression_quality']['interpretation']);
        
        echo "\n✅ API endpoint working correctly!\n";
        
    } else {
        echo "❌ Error in response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
} else {
    echo "❌ HTTP Error: $httpCode\n";
    echo "Response: $response\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
