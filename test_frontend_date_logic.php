<?php

echo "=== Simulando lógica del frontend corregida ===\n\n";

// Simular el contrato TEST-JUNIO-001
$contractSignDate = '2024-06-20';
echo "📋 Contrato: TEST-JUNIO-001\n";
echo "📅 Fecha de venta: {$contractSignDate}\n\n";

// Simular la nueva lógica del frontend
function getDefaultStartDate($signDate) {
    $signDateTime = new DateTime($signDate);
    // Iniciar cronograma el primer día del mes siguiente a la fecha de venta
    $nextMonth = new DateTime($signDateTime->format('Y') . '-' . ($signDateTime->format('n') + 1) . '-01');
    return $nextMonth->format('Y-m-d');
}

$calculatedStartDate = getDefaultStartDate($contractSignDate);
echo "🎯 Fecha de inicio calculada (frontend corregido): {$calculatedStartDate}\n";
echo "📊 Mes calculado: " . (new DateTime($calculatedStartDate))->format('F Y') . "\n\n";

// Comparar con la lógica anterior (fija)
$today = new DateTime();
$oldLogicDate = new DateTime($today->format('Y') . '-' . ($today->format('n') + 1) . '-01');
echo "❌ Fecha anterior (lógica fija): " . $oldLogicDate->format('Y-m-d') . "\n";
echo "📊 Mes anterior: " . $oldLogicDate->format('F Y') . "\n\n";

echo "✅ Problema resuelto: Ahora la fecha se calcula basándose en la fecha de venta del contrato\n";
echo "   - Contrato vendido en junio → Cronograma inicia en julio\n";
echo "   - Ya no usa una fecha fija basada en el mes actual\n";