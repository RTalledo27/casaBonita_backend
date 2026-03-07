<?php
// Script to reset the database and get full error details
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Paso 1: DROP DATABASE casa_bonita ===\n";
    $pdo->exec("DROP DATABASE IF EXISTS casa_bonita");
    echo "OK - Base de datos eliminada\n\n";
    
    echo "=== Paso 2: CREATE DATABASE casa_bonita ===\n";
    $pdo->exec("CREATE DATABASE casa_bonita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK - Base de datos creada\n\n";
    
    echo "=== Verificando ===\n";
    $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=casa_bonita', 'root', '');
    $stm = $pdo2->query('SHOW TABLES');
    $tables = $stm->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas en casa_bonita: " . count($tables) . "\n";
    echo "Base de datos lista para migraciones!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
