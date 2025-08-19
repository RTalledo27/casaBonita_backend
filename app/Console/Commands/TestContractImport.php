<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\app\Services\ContractImportService;
use Illuminate\Support\Facades\DB;
use Exception;

class TestContractImport extends Command
{
    protected $signature = 'test:contract-import {file?}';
    protected $description = 'Test contract import with corrected LotFinancialTemplate fields';

    public function handle()
    {
        $this->info('=== PRUEBA DE IMPORTACIÓN DE CONTRATOS CON CAMPOS CORREGIDOS ===');
        $this->info('Fecha: ' . date('Y-m-d H:i:s'));
        $this->newLine();

        try {
            $service = new \Modules\Sales\Services\ContractImportService();
            
            $filePath = $this->argument('file') ?? 'storage/app/public/imports/contratos_prueba_real.xlsx';
            
            if (!file_exists($filePath)) {
                $this->error("❌ ERROR: Archivo no encontrado: $filePath");
                return 1;
            }
            
            $this->info("📁 Archivo encontrado: $filePath");
            $this->info("📊 Iniciando procesamiento...");
            $this->newLine();
            
            $result = $service->processExcelSimplified($filePath);
            
            $this->info('✅ RESULTADO DEL PROCESAMIENTO:');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->newLine();
            
            // Verificar si se crearon contratos
            $contractsCount = DB::table('contracts')->count();
            $this->info("📋 Total de contratos en la base de datos: $contractsCount");
            
            // Mostrar los últimos contratos creados
            $latestContracts = DB::table('contracts')
                ->orderBy('contract_id', 'desc')
                ->limit(3)
                ->get(['contract_id', 'contract_number', 'total_price', 'down_payment', 'financing_amount', 'monthly_payment']);
            
            if ($latestContracts->count() > 0) {
                $this->newLine();
                $this->info('📋 ÚLTIMOS CONTRATOS CREADOS:');
                foreach ($latestContracts as $contract) {
                    $this->line("- ID: {$contract->contract_id}, Número: {$contract->contract_number}");
                    $this->line("  Total: {$contract->total_price}, Enganche: {$contract->down_payment}");
                    $this->line("  Financiado: {$contract->financing_amount}, Mensualidad: {$contract->monthly_payment}");
                }
            }
            
            return 0;
            
        } catch (Exception $e) {
            $this->error('❌ ERROR: ' . $e->getMessage());
            $this->error('📍 Archivo: ' . $e->getFile());
            $this->error('📍 Línea: ' . $e->getLine());
            $this->newLine();
            $this->error('🔍 STACK TRACE:');
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}