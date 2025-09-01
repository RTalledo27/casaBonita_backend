<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== VERIFICANDO TABLA CONTRACT_IMPORT_LOGS ===\n\n";
    
    // Verificar si la tabla existe
    $tableExists = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'contract_import_logs'");
    
    if ($tableExists[0]->count == 0) {
        echo "❌ La tabla 'contract_import_logs' NO existe\n";
        exit(1);
    }
    
    echo "✅ La tabla 'contract_import_logs' existe\n\n";
    
    // Contar registros totales
    $totalRecords = DB::table('contract_import_logs')->count();
    echo "Total de registros: $totalRecords\n\n";
    
    if ($totalRecords > 0) {
        // Mostrar los últimos 10 registros
        echo "=== ÚLTIMOS 10 REGISTROS ===\n";
        $records = DB::table('contract_import_logs')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        foreach ($records as $record) {
            echo "ID: {$record->import_log_id}\n";
            echo "Usuario: {$record->user_id}\n";
            echo "Archivo: {$record->file_name}\n";
            echo "Estado: {$record->status}\n";
            echo "Filas procesadas: {$record->processed_rows}\n";
            echo "Éxitos: {$record->success_count}\n";
            echo "Errores: {$record->error_count}\n";
            echo "Creado: {$record->created_at}\n";
            echo "---\n";
        }
        
        // Estadísticas por estado
        echo "\n=== ESTADÍSTICAS POR ESTADO ===\n";
        $statusStats = DB::table('contract_import_logs')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
            
        foreach ($statusStats as $stat) {
            echo "{$stat->status}: {$stat->count}\n";
        }
        
        // Estadísticas de procesamiento
        echo "\n=== ESTADÍSTICAS DE PROCESAMIENTO ===\n";
        $processingStats = DB::table('contract_import_logs')
            ->selectRaw('SUM(processed_rows) as total_processed')
            ->selectRaw('SUM(success_count) as total_success')
            ->selectRaw('SUM(error_count) as total_errors')
            ->first();
            
        echo "Total filas procesadas: {$processingStats->total_processed}\n";
        echo "Total éxitos: {$processingStats->total_success}\n";
        echo "Total errores: {$processingStats->total_errors}\n";
        
    } else {
        echo "⚠️ No hay registros en la tabla\n";
        echo "\nEsto significa que:\n";
        echo "1. No se han realizado importaciones recientes\n";
        echo "2. Los logs no se están guardando correctamente\n";
        echo "3. Hay un problema en el proceso de importación\n";
    }
    
    // Verificar contratos recientes
    echo "\n=== CONTRATOS RECIENTES (últimas 24 horas) ===\n";
    $recentContracts = DB::table('contracts')
        ->where('created_at', '>=', now()->subDay())
        ->count();
    echo "Contratos creados: $recentContracts\n";
    
    // Verificar reservaciones recientes
    $recentReservations = DB::table('reservations')
        ->where('created_at', '>=', now()->subDay())
        ->count();
    echo "Reservaciones creadas: $recentReservations\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}