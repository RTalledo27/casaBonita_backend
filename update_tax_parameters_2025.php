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
    echo "║       ACTUALIZACIÓN PARÁMETROS TRIBUTARIOS 2025              ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    // VALORES CORRECTOS SEGÚN GOB.PE 2025
    $uit_2025 = 5350.00; // ✅ Correcto según gob.pe
    $rmv_2025 = 1130.00; // ✅ RMV 2025 = S/ 1,130 (Remuneración Mínima Vital)
    $asignacion_familiar = $rmv_2025 * 0.10; // 10% de RMV = S/ 113.00
    
    echo "📋 VALORES A ACTUALIZAR:\n";
    echo str_repeat("-", 60) . "\n";
    echo "UIT 2025:              S/ " . number_format($uit_2025, 2) . "\n";
    echo "RMV 2025:              S/ " . number_format($rmv_2025, 2) . "\n";
    echo "Asignación Familiar:   S/ " . number_format($asignacion_familiar, 2) . " (10% RMV)\n";
    echo str_repeat("-", 60) . "\n\n";
    
    // Actualizar
    $sql = "UPDATE tax_parameters 
            SET uit_amount = :uit,
                minimum_wage = :rmv,
                family_allowance = :asignacion
            WHERE year = 2025";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uit' => $uit_2025,
        ':rmv' => $rmv_2025,
        ':asignacion' => $asignacion_familiar
    ]);
    
    echo "✅ Parámetros actualizados exitosamente\n\n";
    
    // Verificar
    $stmt = $pdo->query("SELECT * FROM tax_parameters WHERE year = 2025");
    $params = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✨ VALORES ACTUALIZADOS EN LA BD:\n";
    echo str_repeat("=", 60) . "\n";
    echo "UIT 2025:              S/ " . number_format($params['uit_amount'], 2) . "\n";
    echo "RMV 2025:              S/ " . number_format($params['minimum_wage'], 2) . "\n";
    echo "Asignación Familiar:   S/ " . number_format($params['family_allowance'], 2) . "\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Calcular deducción anual
    $deduccion_anual = $uit_2025 * 7;
    echo "💰 DEDUCCIÓN IMPUESTO A LA RENTA:\n";
    echo "7 UIT = 7 × S/ " . number_format($uit_2025, 2) . " = S/ " . number_format($deduccion_anual, 2) . " anuales\n";
    
    echo "\n✅ TODO LISTO! Parámetros correctos según gob.pe 2025\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
