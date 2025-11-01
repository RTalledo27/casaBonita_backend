<?php

// Test the MONTHLY revenue projection endpoint via HTTP
$url = 'http://localhost:8000/v1/reports/projections/revenue?months_ahead=6&months_back=12';

echo "🚀 Testing MONTHLY Revenue Projection API\n";
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
        
        echo "📊 Historical Data (Months):\n";
        foreach ($projection['historical_data'] as $month) {
            echo sprintf("  • %s: %d sales, $%s\n", 
                $month['month_label'],
                $month['sales_count'],
                number_format($month['total_revenue'], 2)
            );
        }
        
        echo "\n🎯 Current Month:\n";
        $current = $projection['current_month'];
        echo sprintf("  • %s: $%s actual\n", 
            $current['month_label'],
            number_format($current['actual_revenue'], 2)
        );
        echo sprintf("  • Progress: %.1f%% (%d/%d days)\n",
            $current['progress_percentage'],
            $current['days_elapsed'],
            $current['days_in_month']
        );
        echo sprintf("  • Projected End: $%s\n",
            number_format($current['projected_month_end'], 2)
        );
        echo sprintf("  • Daily Rate: $%s\n",
            number_format($current['daily_rate'], 2)
        );
        
        echo "\n🔮 Future Projections (Next 6 Months):\n";
        foreach ($projection['projections'] as $proj) {
            echo sprintf("  • %s: $%s (confidence: %.1f%%)\n",
                $proj['month_label'],
                number_format($proj['projected_revenue'], 2),
                $proj['confidence'] * 100
            );
        }
        
        echo "\n📈 Growth Analysis:\n";
        echo sprintf("  • Average Growth Rate: %.2f%%\n", 
            $projection['growth_analysis']['average_growth_rate']
        );
        echo sprintf("  • Trend: %s\n", $projection['summary']['trend']);
        
        echo "\n📊 Regression Quality:\n";
        echo sprintf("  • R²: %.4f\n", $projection['regression_quality']['r_squared']);
        echo sprintf("  • Interpretation: %s\n", $projection['regression_quality']['interpretation']);
        
        echo "\n💡 Summary:\n";
        echo sprintf("  • Total historical months analyzed: %d\n", count($projection['historical_data']));
        echo sprintf("  • Months projected ahead: %d\n", count($projection['projections']));
        
        echo "\n✅ Monthly projection API working correctly!\n";
        
    } else {
        echo "❌ Error in response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
} else {
    echo "❌ HTTP Error: $httpCode\n";
    echo "Response: $response\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
