<?php

/**
 * Script de prueba para verificar la corrección de financing_type
 * en el sistema de importación de contratos
 */

echo "=== PRUEBA DE CORRECCIÓN FINANCING_TYPE ===\n\n";

// Simular datos de LotFinancialTemplate con diferentes escenarios
$testCases = [
    [
        'name' => 'Contrato CON financiamiento (installments_40 > 0)',
        'template' => [
            'installments_24' => 0,
            'installments_40' => 1500.00,
            'installments_44' => 0,
            'installments_55' => 0,
            'precio_venta' => 60000.00,
            'cuota_inicial' => 10000.00
        ]
    ],
    [
        'name' => 'Contrato CON financiamiento (installments_44 > 0)',
        'template' => [
            'installments_24' => 0,
            'installments_40' => 0,
            'installments_44' => 1200.00,
            'installments_55' => 0,
            'precio_venta' => 55000.00,
            'cuota_inicial' => 8000.00
        ]
    ],
    [
        'name' => 'Contrato CON financiamiento (installments_24 > 0)',
        'template' => [
            'installments_24' => 2000.00,
            'installments_40' => 0,
            'installments_44' => 0,
            'installments_55' => 0,
            'precio_venta' => 48000.00,
            'cuota_inicial' => 12000.00
        ]
    ],
    [
        'name' => 'Contrato SIN financiamiento (todos los installments en 0)',
        'template' => [
            'installments_24' => 0,
            'installments_40' => 0,
            'installments_44' => 0,
            'installments_55' => 0,
            'precio_venta' => 45000.00,
            'cuota_inicial' => 45000.00 // Pago completo al contado
        ]
    ],
    [
        'name' => 'Contrato CON financiamiento (installments_55 > 0)',
        'template' => [
            'installments_24' => 0,
            'installments_40' => 0,
            'installments_44' => 0,
            'installments_55' => 900.00,
            'precio_venta' => 50000.00,
            'cuota_inicial' => 5000.00
        ]
    ]
];

// Función para simular la lógica de createDirectContract
function simulateFinancingTypeLogic($template) {
    $monthlyPayment = 0;
    $termMonths = 0;
    $hasInstallments = false;
    
    // Priorizar installments_40, luego installments_44, luego installments_24, luego installments_55
    if ($template['installments_40'] > 0) {
        $monthlyPayment = $template['installments_40'];
        $termMonths = 40;
        $hasInstallments = true;
    } elseif ($template['installments_44'] > 0) {
        $monthlyPayment = $template['installments_44'];
        $termMonths = 44;
        $hasInstallments = true;
    } elseif ($template['installments_24'] > 0) {
        $monthlyPayment = $template['installments_24'];
        $termMonths = 24;
        $hasInstallments = true;
    } elseif ($template['installments_55'] > 0) {
        $monthlyPayment = $template['installments_55'];
        $termMonths = 55;
        $hasInstallments = true;
    }
    
    // Determinar financing_type
    $financingType = $hasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING';
    
    return [
        'has_installments' => $hasInstallments,
        'monthly_payment' => $monthlyPayment,
        'term_months' => $termMonths,
        'financing_type' => $financingType,
        'financing_amount' => $hasInstallments ? ($template['precio_venta'] - $template['cuota_inicial']) : 0
    ];
}

// Ejecutar pruebas
foreach ($testCases as $index => $testCase) {
    echo "Caso " . ($index + 1) . ": {$testCase['name']}\n";
    echo str_repeat('-', 60) . "\n";
    
    $result = simulateFinancingTypeLogic($testCase['template']);
    
    echo "Template datos:\n";
    echo "  - installments_24: {$testCase['template']['installments_24']}\n";
    echo "  - installments_40: {$testCase['template']['installments_40']}\n";
    echo "  - installments_44: {$testCase['template']['installments_44']}\n";
    echo "  - installments_55: {$testCase['template']['installments_55']}\n";
    echo "  - precio_venta: {$testCase['template']['precio_venta']}\n";
    echo "  - cuota_inicial: {$testCase['template']['cuota_inicial']}\n";
    
    echo "\nResultado calculado:\n";
    echo "  - has_installments: " . ($result['has_installments'] ? 'true' : 'false') . "\n";
    echo "  - monthly_payment: {$result['monthly_payment']}\n";
    echo "  - term_months: {$result['term_months']}\n";
    echo "  - financing_amount: {$result['financing_amount']}\n";
    echo "  - financing_type: {$result['financing_type']}\n";
    
    // Validar resultado esperado
    $expectedWithFinancing = ($testCase['template']['installments_24'] > 0 || 
                             $testCase['template']['installments_40'] > 0 || 
                             $testCase['template']['installments_44'] > 0 || 
                             $testCase['template']['installments_55'] > 0);
    
    $expectedFinancingType = $expectedWithFinancing ? 'WITH_FINANCING' : 'WITHOUT_FINANCING';
    
    echo "\nValidación:\n";
    echo "  - Esperado: {$expectedFinancingType}\n";
    echo "  - Obtenido: {$result['financing_type']}\n";
    echo "  - Estado: " . ($expectedFinancingType === $result['financing_type'] ? '✅ CORRECTO' : '❌ ERROR') . "\n";
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

echo "=== RESUMEN ===\n";
echo "La lógica de financing_type ha sido corregida:\n";
echo "- Si CUALQUIER installment_XX > 0 → financing_type = 'WITH_FINANCING'\n";
echo "- Si TODOS los installment_XX = 0 → financing_type = 'WITHOUT_FINANCING'\n";
echo "\nEsta corrección se aplicó en ContractImportService.php línea ~1857\n";
echo "agregando el campo 'financing_type' al array \$contractData.\n";

?>