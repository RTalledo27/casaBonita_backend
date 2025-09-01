<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Services\ContractImportService;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Sales\Models\Contract;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Modules\HumanResources\Models\Employee;
use Exception;

echo "=== PRUEBA DE CREACIÓN DE CONTRATO CON NUEVA LÓGICA ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Buscar un template con installments válidos
    $template = LotFinancialTemplate::whereRaw('(
        installments_24 > 0 OR 
        installments_40 > 0 OR 
        installments_44 > 0 OR 
        installments_55 > 0
    )')
    ->first();
    
    if (!$template) {
        echo "❌ No se encontró template con installments válidos\n";
        exit(1);
    }
    
    echo "✅ Usando template ID: {$template->id}, Lot ID: {$template->lot_id}\n";
    
    // Mostrar installments del template
    echo "Installments del template:\n";
    echo "  - installments_24: {$template->installments_24}\n";
    echo "  - installments_40: {$template->installments_40}\n";
    echo "  - installments_44: {$template->installments_44}\n";
    echo "  - installments_55: {$template->installments_55}\n\n";
    
    // Buscar el lote asociado
    $lot = Lot::where('lot_id', $template->lot_id)->first();
    if (!$lot) {
        echo "❌ No se encontró el lote asociado al template\n";
        exit(1);
    }
    
    // Buscar un cliente y empleado existente
    $client = Client::first();
    $employee = Employee::first();
    
    if (!$client || !$employee) {
        echo "❌ No se encontraron cliente o empleado para la prueba\n";
        exit(1);
    }
    
    echo "✅ Cliente ID: {$client->id}, Empleado ID: {$employee->id}\n\n";
    
    // Datos de prueba para el contrato
    $contractData = [
        'NUMERO_CONTRATO' => 'TEST-' . time(),
        'FECHA_FIRMA' => '2024-01-15',
        'CLIENTE_NOMBRE' => $client->first_name,
        'CLIENTE_APELLIDO' => $client->last_name,
        'CLIENTE_CEDULA' => $client->identification_number,
        'CLIENTE_TELEFONO' => $client->phone ?? '0000000000',
        'CLIENTE_EMAIL' => $client->email ?? 'test@test.com',
        'LOTE_NUMERO' => $lot->num_lot,
        'MANZANA' => $lot->manzana->name ?? 'A',
        'ASESOR_NOMBRE' => $employee->first_name . ' ' . $employee->last_name,
        'PRECIO_TOTAL' => $template->precio_venta,
        'CUOTA_INICIAL' => $template->cuota_inicial,
        'MONTO_FINANCIADO' => $template->precio_venta - $template->cuota_inicial,
        'OBSERVACIONES' => 'Contrato de prueba con nueva lógica de installments'
    ];
    
    echo "Datos del contrato a crear:\n";
    foreach ($contractData as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    echo "\n";
    
    // Crear instancia del servicio
    $importService = new ContractImportService();
    
    // Llamar al método createDirectContract usando reflexión para acceder al método privado
    $reflection = new ReflectionClass($importService);
    $method = $reflection->getMethod('createDirectContract');
    $method->setAccessible(true);
    
    echo "🔄 Creando contrato con nueva lógica...\n";
    $contract = $method->invoke($importService, $client, $lot, $contractData, $employee);
    
    if ($contract) {
        echo "✅ Contrato creado exitosamente!\n";
        echo "Contract ID: {$contract->id}\n";
        echo "Contract Number: {$contract->contract_number}\n";
        echo "Total Price: {$contract->total_price}\n";
        echo "Down Payment: {$contract->down_payment}\n";
        echo "Financing Amount: {$contract->financing_amount}\n";
        echo "Monthly Payment: {$contract->monthly_payment}\n";
        echo "Term Months: {$contract->term_months}\n";
        echo "Interest Rate: {$contract->interest_rate}\n\n";
        
        // Verificar que los valores coincidan con el template
        echo "=== VERIFICACIÓN DE VALORES ===\n";
        
        // Determinar cuál installment debería haberse usado
        $installmentFields = [
            'installments_24' => 24,
            'installments_40' => 40,
            'installments_44' => 44,
            'installments_55' => 55
        ];
        
        $expectedMonthlyPayment = 0;
        $expectedTermMonths = 24;
        $expectedField = 'ninguno';
        
        foreach ($installmentFields as $field => $termMonths) {
            $amount = $template->{$field} ?? 0;
            if ($amount > 0) {
                $expectedMonthlyPayment = $amount;
                $expectedTermMonths = $termMonths;
                $expectedField = $field;
                break;
            }
        }
        
        echo "Valores esperados (del template):\n";
        echo "  - Monthly Payment: {$expectedMonthlyPayment} (campo: {$expectedField})\n";
        echo "  - Term Months: {$expectedTermMonths}\n\n";
        
        echo "Valores del contrato creado:\n";
        echo "  - Monthly Payment: {$contract->monthly_payment}\n";
        echo "  - Term Months: {$contract->term_months}\n\n";
        
        // Verificar coincidencias
        $monthlyMatch = abs($contract->monthly_payment - $expectedMonthlyPayment) < 0.01;
        $termMatch = $contract->term_months == $expectedTermMonths;
        
        echo "Verificación:\n";
        echo "  - Monthly Payment: " . ($monthlyMatch ? '✅ CORRECTO' : '❌ INCORRECTO') . "\n";
        echo "  - Term Months: " . ($termMatch ? '✅ CORRECTO' : '❌ INCORRECTO') . "\n";
        
        if ($monthlyMatch && $termMatch) {
            echo "\n🎉 ¡LA NUEVA LÓGICA FUNCIONA CORRECTAMENTE!\n";
        } else {
            echo "\n❌ La nueva lógica necesita ajustes\n";
        }
        
    } else {
        echo "❌ Error al crear el contrato\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";