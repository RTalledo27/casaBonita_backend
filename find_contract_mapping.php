<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== BÚSQUEDA DE MAPEO DE CONTRATOS ===\n\n";

// Buscar en todas las tablas que puedan contener información de contratos
echo "🔍 BUSCANDO TABLAS CON INFORMACIÓN DE CONTRATOS:\n\n";

// Listar todas las tablas
try {
    $tables = DB::select("SHOW TABLES");
    $contractTables = [];
    
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if (stripos($tableName, 'contract') !== false || 
            stripos($tableName, 'sale') !== false ||
            stripos($tableName, 'deal') !== false ||
            stripos($tableName, 'agreement') !== false) {
            $contractTables[] = $tableName;
        }
    }
    
    echo "Tablas relacionadas con contratos encontradas:\n";
    foreach ($contractTables as $table) {
        echo "- {$table}\n";
    }
    
    // Buscar en cada tabla por el número 20257868
    echo "\n🔍 BUSCANDO '20257868' EN TABLAS DE CONTRATOS:\n";
    
    foreach ($contractTables as $table) {
        try {
            // Obtener columnas de la tabla
            $columns = DB::select("DESCRIBE {$table}");
            $searchColumns = [];
            
            foreach ($columns as $column) {
                if (stripos($column->Type, 'varchar') !== false || 
                    stripos($column->Type, 'text') !== false ||
                    stripos($column->Type, 'char') !== false) {
                    $searchColumns[] = $column->Field;
                }
            }
            
            if (!empty($searchColumns)) {
                $whereClause = [];
                foreach ($searchColumns as $col) {
                    $whereClause[] = "{$col} LIKE '%20257868%'";
                }
                
                $sql = "SELECT * FROM {$table} WHERE " . implode(' OR ', $whereClause) . " LIMIT 5";
                $results = DB::select($sql);
                
                if (!empty($results)) {
                    echo "\n📋 ENCONTRADO EN TABLA: {$table}\n";
                    foreach ($results as $result) {
                        echo "Registro encontrado:\n";
                        foreach ((array)$result as $key => $value) {
                            echo "  {$key}: {$value}\n";
                        }
                        echo "\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error buscando en {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // Buscar también en tablas generales
    echo "\n🔍 BUSCANDO EN OTRAS TABLAS POSIBLES:\n";
    
    $otherTables = ['sales', 'deals', 'projects', 'clients', 'properties'];
    
    foreach ($otherTables as $table) {
        try {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (!empty($exists)) {
                $columns = DB::select("DESCRIBE {$table}");
                $searchColumns = [];
                
                foreach ($columns as $column) {
                    if (stripos($column->Type, 'varchar') !== false || 
                        stripos($column->Type, 'text') !== false ||
                        stripos($column->Type, 'char') !== false) {
                        $searchColumns[] = $column->Field;
                    }
                }
                
                if (!empty($searchColumns)) {
                    $whereClause = [];
                    foreach ($searchColumns as $col) {
                        $whereClause[] = "{$col} LIKE '%20257868%'";
                    }
                    
                    $sql = "SELECT * FROM {$table} WHERE " . implode(' OR ', $whereClause) . " LIMIT 3";
                    $results = DB::select($sql);
                    
                    if (!empty($results)) {
                        echo "\n📋 ENCONTRADO EN TABLA: {$table}\n";
                        foreach ($results as $result) {
                            echo "Registro encontrado:\n";
                            foreach ((array)$result as $key => $value) {
                                echo "  {$key}: {$value}\n";
                            }
                            echo "\n";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error buscando en {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // Mostrar algunas comisiones de ejemplo para entender la estructura
    echo "\n📊 EJEMPLOS DE COMISIONES EXISTENTES:\n";
    $sampleCommissions = DB::select("
        SELECT c.commission_id, c.contract_id, c.employee_id, c.commission_amount, c.payment_part, c.is_payable
        FROM commissions c
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    
    foreach ($sampleCommissions as $commission) {
        echo "- Commission ID: {$commission->commission_id}, Contract ID: {$commission->contract_id}, Employee: {$commission->employee_id}, Part: " . ($commission->payment_part ?? 'NULL') . "\n";
    }
    
    // Mostrar algunas cuentas por cobrar de ejemplo
    echo "\n💰 EJEMPLOS DE CUENTAS POR COBRAR:\n";
    $sampleAR = DB::select("
        SELECT ar_id, contract_id, original_amount, due_date, status
        FROM accounts_receivable
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    foreach ($sampleAR as $ar) {
        echo "- AR ID: {$ar->ar_id}, Contract ID: {$ar->contract_id}, Monto: $" . number_format($ar->original_amount, 2) . ", Estado: {$ar->status}\n";
    }
    
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA BÚSQUEDA DE MAPEO ===\n";