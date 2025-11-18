<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 Testing Advisor Matching Algorithm\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Casos de prueba reales de Logicware
$testCases = [
    'PAOLA CANDELA',
    'DAVID FEIJOO',
    'FERNANDO DAVID',
    'LUIS GARCIA',
    'MARIA TORRES'
];

// Obtener todos los asesores
$allAdvisors = Employee::whereHas('user')->with('user')->get();

echo "📊 Asesores en la base de datos:\n";
foreach ($allAdvisors as $advisor) {
    $fullName = ($advisor->user->first_name ?? '') . ' ' . ($advisor->user->last_name ?? '');
    echo "   • ID {$advisor->employee_id}: {$fullName}\n";
}
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Función de matching (igual a la del importer)
function findBestMatch($sellerName, $allAdvisors) {
    $sellerName = trim($sellerName);
    $sellerParts = array_filter(explode(' ', strtoupper($sellerName)));

    $bestMatch = null;
    $bestScore = 0;
    $allScores = [];

    foreach ($allAdvisors as $advisor) {
        $firstName = strtoupper($advisor->user->first_name ?? '');
        $lastName = strtoupper($advisor->user->last_name ?? '');
        $advisorFullName = trim($firstName . ' ' . $lastName);
        $advisorParts = array_filter(explode(' ', $advisorFullName));
        
        $score = 0;
        $matchedParts = 0;
        $details = [];
        
        foreach ($sellerParts as $sellerPart) {
            $foundExactMatch = false;
            $foundPartialMatch = false;
            
            foreach ($advisorParts as $advisorPart) {
                if ($advisorPart === $sellerPart) {
                    $score += 100;
                    $foundExactMatch = true;
                    $matchedParts++;
                    $details[] = "✓ Exact match: '$sellerPart' = '$advisorPart' (+100)";
                    break;
                } elseif (stripos($advisorPart, $sellerPart) !== false) {
                    $score += 50;
                    $foundPartialMatch = true;
                    $matchedParts++;
                    $details[] = "≈ Partial match: '$sellerPart' in '$advisorPart' (+50)";
                    break;
                }
            }
            
            if (!$foundExactMatch && !$foundPartialMatch) {
                if (stripos($firstName, $sellerPart) !== false) {
                    $score += 30;
                    $matchedParts++;
                    $details[] = "~ Found in first name: '$sellerPart' (+30)";
                } elseif (stripos($lastName, $sellerPart) !== false) {
                    $score += 30;
                    $matchedParts++;
                    $details[] = "~ Found in last name: '$sellerPart' (+30)";
                }
            }
        }
        
        if ($matchedParts === count($sellerParts)) {
            $score += 500;
            $details[] = "🎯 ALL PARTS MATCHED (+500 BONUS)";
        }
        
        if ($score > 0) {
            $allScores[] = [
                'advisor' => $advisorFullName,
                'advisor_id' => $advisor->employee_id,
                'score' => $score,
                'details' => $details
            ];
        }
        
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $advisor;
        }
    }

    return [
        'match' => $bestMatch,
        'score' => $bestScore,
        'all_scores' => $allScores
    ];
}

// Probar cada caso
foreach ($testCases as $testCase) {
    echo "🔍 Testing: '$testCase'\n";
    echo "─────────────────────────────────────────────\n";
    
    $result = findBestMatch($testCase, $allAdvisors);
    
    if ($result['match'] && $result['score'] >= 100) {
        $matchName = ($result['match']->user->first_name ?? '') . ' ' . ($result['match']->user->last_name ?? '');
        echo "✅ MATCH FOUND: {$matchName} (ID: {$result['match']->employee_id})\n";
        echo "   Score: {$result['score']}\n";
    } else {
        echo "❌ NO MATCH (score: {$result['score']}, required: 100)\n";
    }
    
    echo "\n📊 All scores:\n";
    usort($result['all_scores'], fn($a, $b) => $b['score'] - $a['score']);
    foreach (array_slice($result['all_scores'], 0, 3) as $scoreData) {
        echo "   • {$scoreData['advisor']} (ID {$scoreData['advisor_id']}): {$scoreData['score']} points\n";
        foreach ($scoreData['details'] as $detail) {
            echo "     {$detail}\n";
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
}
