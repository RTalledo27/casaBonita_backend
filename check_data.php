<?php

// Script simple para verificar datos
echo "Verificando datos...\n";

// Usar conexión directa a la base de datos
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=casa_bonita', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conexión a base de datos exitosa\n\n";
    
    // Verificar empleados
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $employees = $stmt->fetch();
    echo "Total empleados: " . $employees['count'] . "\n";
    
    // Verificar comisiones
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM commissions");
    $commissions = $stmt->fetch();
    echo "Total comisiones: " . $commissions['count'] . "\n";
    
    // Verificar bonos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bonuses");
    $bonuses = $stmt->fetch();
    echo "Total bonos: " . $bonuses['count'] . "\n";
    
    // Verificar estructura de tablas
    echo "\nEstructura de tabla commissions:\n";
    $stmt = $pdo->query("DESCRIBE commissions");
    while ($row = $stmt->fetch()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\nEstructura de tabla bonuses:\n";
    $stmt = $pdo->query("DESCRIBE bonuses");
    while ($row = $stmt->fetch()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    // Si hay comisiones, mostrar algunas
    if ($commissions['count'] > 0) {
        echo "\nPrimeras 5 comisiones (todas las columnas):\n";
        $stmt = $pdo->query("SELECT * FROM commissions LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "  Registro: " . json_encode($row) . "\n";
        }
    }
    
    // Si hay bonos, mostrar algunos
    if ($bonuses['count'] > 0) {
        echo "\nPrimeros 5 bonos (todas las columnas):\n";
        $stmt = $pdo->query("SELECT * FROM bonuses LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "  Registro: " . json_encode($row) . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
    
    // Intentar con diferentes configuraciones
    echo "\nIntentando con diferentes configuraciones...\n";
    
    $configs = [
        ['host' => 'localhost', 'db' => 'casa_bonita', 'user' => 'root', 'pass' => ''],
        ['host' => '127.0.0.1', 'db' => 'casabonita', 'user' => 'root', 'pass' => ''],
        ['host' => 'localhost', 'db' => 'casabonita', 'user' => 'root', 'pass' => ''],
    ];
    
    foreach ($configs as $config) {
        try {
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']}", $config['user'], $config['pass']);
            echo "Conexión exitosa con: host={$config['host']}, db={$config['db']}\n";
            break;
        } catch (PDOException $e2) {
            echo "Falló: host={$config['host']}, db={$config['db']} - " . $e2->getMessage() . "\n";
        }
    }
}

echo "\nFin de verificación\n";