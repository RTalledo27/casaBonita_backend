<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 Testing Revenue Projection Backend\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Test 1: Get quarterly revenue data
    echo "📊 Test 1: Get Quarterly Revenue Data\n";
    echo str_repeat("-", 60) . "\n";
    
    $repo = new Modules\Reports\Repositories\ProjectionRepository();
    $quarterlyData = $repo->getQuarterlyRevenue(2);
    
    echo "Found " . count($quarterlyData) . " quarters of data:\n";
    foreach ($quarterlyData as $quarter) {
        echo sprintf(
            "  %s: %d sales, $%.2f revenue\n",
            $quarter['quarter_label'],
            $quarter['sales_count'],
            $quarter['total_revenue']
        );
    }
    echo "\n";

    // Test 2: Calculate linear regression
    echo "📈 Test 2: Linear Regression Analysis\n";
    echo str_repeat("-", 60) . "\n";
    
    $regression = $repo->calculateLinearRegression($quarterlyData);
    echo sprintf("Slope (m): %.2f\n", $regression['slope']);
    echo sprintf("Intercept (b): %.2f\n", $regression['intercept']);
    echo sprintf("R² (quality): %.4f\n", $regression['r_squared']);
    
    if ($regression['r_squared'] >= 0.7) {
        echo "✅ Excelente calidad de proyección\n";
    } elseif ($regression['r_squared'] >= 0.5) {
        echo "⚠️ Calidad moderada de proyección\n";
    } else {
        echo "❌ Baja calidad de proyección (datos muy variables)\n";
    }
    echo "\n";

    // Test 3: Project future quarters
    echo "🔮 Test 3: Project Future Quarters\n";
    echo str_repeat("-", 60) . "\n";
    
    $projections = $repo->projectFutureQuarters($quarterlyData, 4);
    echo "Projected next 4 quarters:\n";
    foreach ($projections as $proj) {
        echo sprintf(
            "  %s: $%.2f (confidence: %.2f%%)\n",
            $proj['quarter_label'],
            $proj['projected_revenue'],
            $proj['confidence'] * 100
        );
    }
    echo "\n";

    // Test 4: Calculate growth rates
    echo "📊 Test 4: Growth Rate Analysis\n";
    echo str_repeat("-", 60) . "\n";
    
    $growthAnalysis = $repo->calculateGrowthRates($quarterlyData);
    echo sprintf("Average Growth Rate: %.2f%%\n", $growthAnalysis['average_growth_rate']);
    
    if (!empty($growthAnalysis['quarterly_growth'])) {
        echo "\nQuarterly Changes:\n";
        $recent = array_slice($growthAnalysis['quarterly_growth'], -3);
        foreach ($recent as $growth) {
            $arrow = $growth['growth_rate'] > 0 ? "📈" : "📉";
            echo sprintf(
                "  %s %s: %+.2f%% ($%.2f)\n",
                $arrow,
                $growth['quarter_label'],
                $growth['growth_rate'],
                $growth['absolute_change']
            );
        }
    }
    echo "\n";

    // Test 5: Current quarter performance
    echo "🎯 Test 5: Current Quarter Performance\n";
    echo str_repeat("-", 60) . "\n";
    
    $currentQuarter = $repo->getCurrentQuarterRevenue();
    echo sprintf("Quarter: %s\n", $currentQuarter['quarter_label']);
    echo sprintf("Actual Revenue: $%.2f\n", $currentQuarter['actual_revenue']);
    echo sprintf("Sales Count: %d\n", $currentQuarter['sales_count']);
    echo sprintf("Progress: %.2f%% (%d of %d days)\n", 
        $currentQuarter['progress_percentage'],
        $currentQuarter['days_elapsed'],
        $currentQuarter['days_in_quarter']
    );
    echo sprintf("Projected Quarter End: $%.2f\n", $currentQuarter['projected_quarter_end']);
    echo sprintf("Daily Rate: $%.2f/day\n", $currentQuarter['daily_rate']);
    echo "\n";

    // Test 6: Seasonal patterns
    echo "🌦️ Test 6: Seasonal Pattern Detection\n";
    echo str_repeat("-", 60) . "\n";
    
    $seasonalFactors = $repo->detectSeasonality($quarterlyData);
    echo "Seasonal factors (1.0 = average):\n";
    foreach ($seasonalFactors as $quarter => $factor) {
        $indicator = $factor > 1.1 ? "🔥 High" : ($factor < 0.9 ? "❄️ Low" : "➡️ Normal");
        echo sprintf("  %s: %.2fx %s\n", $quarter, $factor, $indicator);
    }
    echo "\n";

    // Test 7: Full projection service
    echo "🎬 Test 7: Complete Projection Service\n";
    echo str_repeat("-", 60) . "\n";
    
    $service = new Modules\Reports\Services\ProjectionService($repo);
    $fullProjection = $service->getRevenueProjection(4, 2);
    
    echo "Summary:\n";
    $summary = $fullProjection['summary'];
    echo sprintf("  Last Quarter Revenue: $%.2f\n", $summary['last_quarter_revenue']);
    echo sprintf("  Current Quarter Actual: $%.2f\n", $summary['current_quarter_actual']);
    echo sprintf("  Next Quarter Projection: $%.2f\n", $summary['next_quarter_projection']);
    echo sprintf("  Average Growth Rate: %.2f%%\n", $summary['average_growth_rate']);
    echo sprintf("  Trend: %s\n", $summary['trend']);
    
    echo "\nRegression Quality:\n";
    $quality = $fullProjection['regression_quality'];
    echo sprintf("  R²: %.4f\n", $quality['r_squared']);
    echo sprintf("  Interpretation: %s\n", $quality['interpretation']);
    
    echo "\n✅ All tests completed successfully!\n";
    echo "\n📝 Key Insights:\n";
    echo "  • Found " . count($quarterlyData) . " quarters of historical data\n";
    echo "  • Regression quality (R²): " . round($regression['r_squared'], 2) . "\n";
    echo "  • Average growth rate: " . round($growthAnalysis['average_growth_rate'], 2) . "%\n";
    echo "  • Current quarter progress: " . round($currentQuarter['progress_percentage'], 1) . "%\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 Test Complete\n";
