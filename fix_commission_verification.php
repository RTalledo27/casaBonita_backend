<?php
require_once 'vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Conexión a la base de datos
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== CORRIGIENDO PROBLEMA DE VERIFICACIÓN DE COMISIONES ===\n\n";
    
    $contractId = 50; // CON20257868
    
    // 1. Actualizar el status en payment_schedules de 'pagado' a 'PAID'
    echo "1. Actualizando status en payment_schedules:\n";
    $stmt = $pdo->prepare("
        UPDATE payment_schedules 
        SET status = 'PAID' 
        WHERE contract_id = ? 
        AND installment_number IN (1, 2) 
        AND status = 'pagado'
    ");
    $result = $stmt->execute([$contractId]);
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        echo "  ✓ Actualizadas {$rowsAffected} cuotas en payment_schedules\n";
    } else {
        echo "  ⚠ No se actualizaron cuotas en payment_schedules\n";
    }
    
    // 2. Actualizar accounts_receivable para marcar las cuotas como pagadas
    echo "\n2. Actualizando accounts_receivable:\n";
    
    // Primero verificar el estado actual
    $stmt = $pdo->prepare("
        SELECT ar_id, description, status, outstanding_amount 
        FROM accounts_receivable 
        WHERE contract_id = ? 
        AND (description LIKE '%Cuota #1 %' OR description LIKE '%Cuota #2 %')
    ");
    $stmt->execute([$contractId]);
    $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cuotas as $cuota) {
        echo "  Procesando: {$cuota['description']}\n";
        echo "    Estado actual: {$cuota['status']}\n";
        echo "    Outstanding: {$cuota['outstanding_amount']}\n";
        
        // Actualizar a PAID y outstanding_amount a 0
        $updateStmt = $pdo->prepare("
            UPDATE accounts_receivable 
            SET status = 'PAID', 
                outstanding_amount = 0,
                updated_at = NOW()
            WHERE ar_id = ?
        ");
        $updateResult = $updateStmt->execute([$cuota['ar_id']]);
        
        if ($updateResult) {
            echo "    ✓ Actualizada a PAID con outstanding_amount = 0\n";
        } else {
            echo "    ✗ Error al actualizar\n";
        }
        echo "    ---\n";
    }
    
    // 3. Verificar que los cambios se aplicaron correctamente
    echo "\n3. Verificando cambios aplicados:\n";
    
    // Verificar payment_schedules
    $stmt = $pdo->prepare("
        SELECT installment_number, status 
        FROM payment_schedules 
        WHERE contract_id = ? 
        AND installment_number IN (1, 2)
    ");
    $stmt->execute([$contractId]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Payment Schedules:\n";
    foreach ($schedules as $schedule) {
        echo "    - Cuota {$schedule['installment_number']}: {$schedule['status']}\n";
    }
    
    // Verificar accounts_receivable
    $stmt = $pdo->prepare("
        SELECT description, status, outstanding_amount 
        FROM accounts_receivable 
        WHERE contract_id = ? 
        AND (description LIKE '%Cuota #1 %' OR description LIKE '%Cuota #2 %')
    ");
    $stmt->execute([$contractId]);
    $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n  Accounts Receivable:\n";
    foreach ($cuotas as $cuota) {
        echo "    - {$cuota['description']}: {$cuota['status']} (Outstanding: {$cuota['outstanding_amount']})\n";
    }
    
    // 4. Probar la lógica de verificación actualizada
    echo "\n4. Probando lógica de verificación actualizada:\n";
    
    // Contar cuotas pagadas usando diferentes criterios
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as paid_count_ar
        FROM accounts_receivable 
        WHERE contract_id = ? 
        AND (description LIKE '%Cuota #1 %' OR description LIKE '%Cuota #2 %')
        AND (status = 'PAID' OR outstanding_amount = 0)
    ");
    $stmt->execute([$contractId]);
    $paidCountAR = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as paid_count_ps
        FROM payment_schedules 
        WHERE contract_id = ? 
        AND installment_number IN (1, 2)
        AND status = 'PAID'
    ");
    $stmt->execute([$contractId]);
    $paidCountPS = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "  Cuotas pagadas según accounts_receivable: {$paidCountAR['paid_count_ar']}/2\n";
    echo "  Cuotas pagadas según payment_schedules: {$paidCountPS['paid_count_ps']}/2\n";
    
    if ($paidCountAR['paid_count_ar'] == 2 && $paidCountPS['paid_count_ps'] == 2) {
        echo "\n  ✅ PROBLEMA RESUELTO:\n";
        echo "  Las cuotas 1 y 2 ahora están correctamente marcadas como pagadas\n";
        echo "  El sistema de verificación de comisiones debería detectarlas ahora\n";
    } else {
        echo "\n  ⚠ PROBLEMA PARCIALMENTE RESUELTO:\n";
        echo "  Revisar manualmente los datos actualizados\n";
    }
    
    // 5. Mostrar el impacto en las comisiones
    echo "\n5. Impacto en las comisiones:\n";
    echo "  Con las cuotas 1 y 2 marcadas como pagadas, las comisiones\n";
    echo "  del empleado EMP6303 deberían activarse automáticamente\n";
    echo "  en el próximo proceso de verificación.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>