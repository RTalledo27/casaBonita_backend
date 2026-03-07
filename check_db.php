<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=casa_bonita', 'root', '');
    echo "Conexion OK\n";
    $stm = $pdo->query('SHOW TABLES');
    $tables = $stm->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas en casa_bonita (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo " - $t\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Try without dbname
    try {
        $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
        echo "Conexion sin DB OK\n";
        $stm2 = $pdo2->query('SHOW DATABASES');
        $dbs = $stm2->fetchAll(PDO::FETCH_COLUMN);
        echo "Databases encontradas:\n";
        foreach ($dbs as $db) {
            echo " - $db\n";
        }
    } catch (Exception $e2) {
        echo "ERROR sin DB: " . $e2->getMessage() . "\n";
    }
}
