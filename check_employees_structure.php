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
    
    echo "Verificando estructura de la tabla 'employees'...\n\n";
    
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columnas actuales:\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-30s %-30s %-10s %-10s\n", "Field", "Type", "Null", "Default");
    echo str_repeat("=", 100) . "\n";
    
    foreach ($columns as $column) {
        printf("%-30s %-30s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\n\nVerificando campos específicos del sistema pensionario:\n";
    echo str_repeat("-", 60) . "\n";
    
    $fieldsToCheck = [
        'pension_system',
        'afp_provider',
        'cuspp',
        'has_family_allowance',
        'number_of_children',
        'department',
        'position'
    ];
    
    foreach ($fieldsToCheck as $field) {
        $exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $field) {
                $exists = true;
                break;
            }
        }
        
        if ($exists) {
            echo "✅ Campo '$field' YA EXISTE\n";
        } else {
            echo "❌ Campo '$field' NO EXISTE (necesita agregarse)\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
