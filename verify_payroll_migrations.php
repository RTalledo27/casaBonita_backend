<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$database = $_ENV['DB_DATABASE'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║         VERIFICACIÓN DE TABLAS - SISTEMA DE NÓMINAS          ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. Verificar tabla PAYROLLS
    echo "1️⃣  TABLA: payrolls\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("DESCRIBE payrolls");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fieldsToCheck = [
        'family_allowance',
        'pension_system',
        'afp_provider',
        'afp_contribution',
        'afp_commission',
        'afp_insurance',
        'onp_contribution',
        'total_pension',
        'rent_tax_5th',
        'employer_essalud'
    ];
    
    foreach ($fieldsToCheck as $field) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $field) {
                $exists = true;
                echo "✅ " . str_pad($field, 25) . " | " . $column['Type'] . "\n";
                break;
            }
        }
        
        if (!$exists) {
            echo "❌ " . str_pad($field, 25) . " | NO EXISTE\n";
        }
    }
    
    // Verificar que se eliminaron las columnas viejas
    echo "\n🗑️  Columnas que debieron eliminarse:\n";
    $oldFields = ['social_security', 'health_insurance', 'income_tax'];
    foreach ($oldFields as $field) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $field) {
                $exists = true;
                break;
            }
        }
        
        if ($exists) {
            echo "⚠️  '$field' aún existe (debería haberse eliminado)\n";
        } else {
            echo "✅ '$field' fue eliminado correctamente\n";
        }
    }
    
    // 2. Verificar tabla TAX_PARAMETERS
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "2️⃣  TABLA: tax_parameters\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("SELECT * FROM tax_parameters WHERE year = 2025");
    $taxParams = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($taxParams) {
        echo "✅ Parámetros 2025 encontrados:\n\n";
        
        echo "💰 VALORES BASE:\n";
        echo "   UIT 2025: S/ " . number_format($taxParams['uit_amount'], 2) . "\n";
        echo "   Asignación Familiar: S/ " . number_format($taxParams['family_allowance'], 2) . "\n";
        echo "   RMV: S/ " . number_format($taxParams['minimum_wage'], 2) . "\n";
        
        echo "\n📊 AFP:\n";
        echo "   Aporte: " . $taxParams['afp_contribution_rate'] . "%\n";
        echo "   Seguro: " . $taxParams['afp_insurance_rate'] . "%\n";
        echo "   Comisión Prima: " . $taxParams['afp_prima_commission'] . "%\n";
        echo "   Comisión Integra: " . $taxParams['afp_integra_commission'] . "%\n";
        echo "   Comisión Profuturo: " . $taxParams['afp_profuturo_commission'] . "%\n";
        echo "   Comisión Habitat: " . $taxParams['afp_habitat_commission'] . "%\n";
        
        echo "\n🏛️  ONP:\n";
        echo "   Tasa: " . $taxParams['onp_rate'] . "%\n";
        
        echo "\n🏥 ESSALUD:\n";
        echo "   Tasa Empleador: " . $taxParams['essalud_rate'] . "%\n";
        
        echo "\n💵 IMPUESTO A LA RENTA:\n";
        echo "   Deducción: " . $taxParams['rent_tax_deduction_uit'] . " UIT\n";
        echo "   Tramo 1 (hasta " . $taxParams['rent_tax_tramo1_uit'] . " UIT): " . $taxParams['rent_tax_tramo1_rate'] . "%\n";
        echo "   Tramo 2 (hasta " . $taxParams['rent_tax_tramo2_uit'] . " UIT): " . $taxParams['rent_tax_tramo2_rate'] . "%\n";
        echo "   Tramo 3 (hasta " . $taxParams['rent_tax_tramo3_uit'] . " UIT): " . $taxParams['rent_tax_tramo3_rate'] . "%\n";
        echo "   Tramo 4 (hasta " . $taxParams['rent_tax_tramo4_uit'] . " UIT): " . $taxParams['rent_tax_tramo4_rate'] . "%\n";
        echo "   Tramo 5 (más de " . $taxParams['rent_tax_tramo4_uit'] . " UIT): " . $taxParams['rent_tax_tramo5_rate'] . "%\n";
        
    } else {
        echo "❌ No se encontraron parámetros para 2025\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✨ VERIFICACIÓN COMPLETADA\n";
    echo str_repeat("=", 80) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
