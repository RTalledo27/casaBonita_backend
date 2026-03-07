<?php
// Probar la tabla de migrations manualmente
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=casa_bonita', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla migrations manualmente
    $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `migration` varchar(255) NOT NULL,
        `batch` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Tabla migrations creada exitosamente!\n";
    
    // Verificar
    $stm = $pdo->query('SHOW TABLES');
    $tables = $stm->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas: " . implode(', ', $tables) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
