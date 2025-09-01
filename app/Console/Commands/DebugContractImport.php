<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Services\ContractImportService;
use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Client;
use Modules\Sales\Models\Employee;
use ReflectionClass;

class DebugContractImport extends Command
{
    protected $signature = 'debug:contract-import';
    protected $description = 'Debug contract import process with specific data';

    public function handle()
    {
        $this->info('=== INICIANDO DEBUG DE CREACIÓN DE CONTRATOS ===');

        // Headers como vienen del Excel (simulando la primera fila)
        $headers = [
            'ASESOR_NOMBRE',
            'ASESOR_CODIGO', 
            'ASESOR_EMAIL',
            'CLIENTE_NOMBRE_COMPLETO',
            'CLIENTE_TIPO_DOC',
            'CLIENTE_NUM_DOC',
            'CLIENTE_TELEFONO_1',
            'CLIENTE_EMAIL',
            'LOTE_NUMERO',
            'LOTE_MANZANA',
            'FECHA_VENTA',
            'TIPO_OPERACION',
            'OBSERVACIONES',
            'ESTADO_CONTRATO'
        ];
        
        // Datos como vienen del Excel (simulando una fila de datos)
        $testData = [
            'ALISSON TORRES',    // posición 0
            '-',                 // posición 1
            '-',                 // posición 2
            'LUZ AURORA ARMIJOS ROBLEDO', // posición 3
            'DNI',               // posición 4
            '-',                 // posición 5
            '950285502',         // posición 6
            '-',                 // posición 7
            '5',                 // posición 8
            'H',                 // posición 9
            '02/06/2025',        // posición 10
            'contrato',          // posición 11
            'LEAD KOMMO',        // posición 12
            'ACTIVO'             // posición 13
        ];

        $this->info('\n=== DATOS DE PRUEBA ===');
        foreach ($testData as $key => $value) {
            $this->line("$key: '$value'");
        }

        // Crear instancia del servicio
        $importService = new ContractImportService();

        // Usar reflection para acceder a métodos privados
        $reflection = new ReflectionClass($importService);

        $this->info('\n=== PASO 1: MAPEO DE HEADERS ===');
        $mapHeadersMethod = $reflection->getMethod('mapSimplifiedHeaders');
        $mapHeadersMethod->setAccessible(true);
        $mappedHeaders = $mapHeadersMethod->invoke($importService, $headers);
        $this->line('Headers mapeados:');
        foreach ($mappedHeaders as $key => $value) {
            $this->line("  $key => $value");
        }

        $this->info('\n=== PASO 2: MAPEO DE DATOS DE FILA ===');
        $mapRowMethod = $reflection->getMethod('mapRowDataSimplified');
        $mapRowMethod->setAccessible(true);
        $mappedData = $mapRowMethod->invoke($importService, $testData, $headers);
        $this->line('Datos mapeados:');
        foreach ($mappedData as $key => $value) {
            $this->line("  $key => '$value'");
        }

        $this->info('\n=== PASO 3: VERIFICAR SI DEBE CREAR CONTRATO ===');
        $shouldCreateMethod = $reflection->getMethod('shouldCreateContractSimplified');
        $shouldCreateMethod->setAccessible(true);
        $shouldCreate = $shouldCreateMethod->invoke($importService, $mappedData);

        $this->line('¿Debe crear contrato? ' . ($shouldCreate ? 'SÍ' : 'NO'));
        $this->line("operation_type en datos mapeados: '" . ($mappedData['operation_type'] ?? 'NO_DEFINIDO') . "'");
        $this->line("contract_status en datos mapeados: '" . ($mappedData['contract_status'] ?? 'NO_DEFINIDO') . "'");

        // Verificar valores específicos
        $this->info('\n=== ANÁLISIS DETALLADO ===');
        $this->line("Valor original TIPO_OPERACION: '" . $testData[11] . "'"); // posición 11
        $this->line("Valor mapeado operation_type: '" . ($mappedData['operation_type'] ?? 'NO_DEFINIDO') . "'");
        $this->line("Valor original ESTADO_CONTRATO: '" . $testData[13] . "'"); // posición 13
        $this->line("Valor mapeado contract_status: '" . ($mappedData['contract_status'] ?? 'NO_DEFINIDO') . "'");

        // Verificar condiciones específicas
        $tipoOperacion = strtolower($mappedData['operation_type'] ?? '');
        $estadoContrato = strtolower($mappedData['contract_status'] ?? '');

        $this->info('\n=== EVALUACIÓN DE CONDICIONES ===');
        $this->line("operation_type en minúsculas: '$tipoOperacion'");
        $this->line("contract_status en minúsculas: '$estadoContrato'");
        $this->line('¿operation_type es \'venta\' o \'contrato\'? ' . (in_array($tipoOperacion, ['venta', 'contrato']) ? 'SÍ' : 'NO'));
        $this->line('¿contract_status es \'vigente\', \'activo\' o \'firmado\'? ' . (in_array($estadoContrato, ['vigente', 'activo', 'firmado']) ? 'SÍ' : 'NO'));

        // Verificar si existe el lote
        $this->info('\n=== VERIFICACIÓN DE LOTE ===');
        $lot = Lot::where('num_lot', $mappedData['lot_number'] ?? '')
                   ->where('manzana_id', $mappedData['lot_manzana'] ?? '')
                   ->first();

        if ($lot) {
            $this->line("Lote encontrado: ID {$lot->lot_id}, Número {$lot->num_lot}, Manzana {$lot->manzana}");
            $this->line('¿Tiene template financiero? ' . ($lot->financialTemplate ? 'SÍ' : 'NO'));
            if ($lot->financialTemplate) {
                $this->line("Template ID: {$lot->financialTemplate->template_id}");
            }
        } else {
            $this->error("LOTE NO ENCONTRADO con número: '" . ($mappedData['lot_number'] ?? 'NO_DEFINIDO') . "' y manzana: '" . ($mappedData['lot_manzana'] ?? 'NO_DEFINIDO') . "'");
        }

        // Verificar si existe el cliente
        $this->info('\n=== VERIFICACIÓN DE CLIENTE ===');
        $clientName = $mappedData['cliente_nombres'] ?? '';
        if ($clientName) {
            $client = Client::where('full_name', 'LIKE', "%$clientName%")->first();
            if ($client) {
                $this->line("Cliente encontrado: ID {$client->client_id}, Nombre: {$client->full_name}");
            } else {
                $this->line("Cliente no encontrado con nombre: '$clientName'");
            }
        } else {
            $this->line('Nombre de cliente no definido en datos mapeados');
        }

        $this->info('\n=== FIN DEL DEBUG ===');
        return 0;
    }
}