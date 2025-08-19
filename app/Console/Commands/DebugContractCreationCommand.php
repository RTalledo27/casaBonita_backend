<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\Inventory\Models\Lot;
use Modules\CRM\Models\Client;
use Modules\HumanResources\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DebugContractCreationCommand extends Command
{
    protected $signature = 'debug:contract-creation';
    protected $description = 'Debug contract creation with real data';

    public function handle()
    {
        $this->info('=== DEBUG CREACIÓN DE CONTRATOS ===');
        
        try {
            // Obtener datos reales de la base de datos
            $lot = Lot::with('financialTemplate')->where('num_lot', 1)->whereHas('manzana', function($q) {
                $q->where('name', 'A');
            })->first();
            
            if (!$lot) {
                $this->error('No se encontró el lote 1 de la manzana A');
                return;
            }
            
            $this->info('Lote encontrado: ' . $lot->lot_id);
            
            if (!$lot->financialTemplate) {
                $this->error('El lote no tiene template financiero');
                return;
            }
            
            $template = $lot->financialTemplate;
            $this->info('Template financiero encontrado');
            $this->info('Precio venta: ' . $template->precio_venta);
            $this->info('Cuota inicial: ' . $template->cuota_inicial);
            
            // Crear cliente de prueba
            $client = Client::create([
                'first_name' => 'TEST',
                'last_name' => 'CLIENT',
                'document_type' => 'DNI',
                'document_number' => '99999999',
                'email' => 'test@test.com',
                'phone' => '999999999'
            ]);
            
            $this->info('Cliente creado: ' . $client->client_id);
            
            // Crear asesor de prueba
            $advisor = Employee::where('employee_type', 'asesor_inmobiliario')->first();
            if (!$advisor) {
                $this->error('No hay asesores disponibles');
                return;
            }
            
            $this->info('Asesor encontrado: ' . $advisor->employee_id);
            
            // Crear reservación
            $reservation = Reservation::create([
                'client_id' => $client->client_id,
                'lot_id' => $lot->lot_id,
                'advisor_id' => $advisor->employee_id,
                'reservation_date' => now()->format('Y-m-d'),
                'expiration_date' => now()->addDays(30)->format('Y-m-d'),
                'sale_date' => now()->format('Y-m-d'),
                'deposit_amount' => $template->cuota_inicial ?? 0,
                'reference' => 'DEBUG-' . date('YmdHis'),
                'status' => 'pendiente_pago'
            ]);
            
            $this->info('Reservación creada: ' . $reservation->reservation_id);
            
            // Preparar datos del contrato
            $contractData = [
                'reservation_id' => $reservation->reservation_id,
                'contract_number' => 'DEBUG-' . date('YmdHis'),
                'sign_date' => now()->format('Y-m-d'),
                'total_price' => $template->getEffectivePrice(),
                'down_payment' => $template->cuota_inicial ?? 0,
                'financing_amount' => $template->getFinancingAmount(),
                'monthly_payment' => $template->installments_40 ?? 0,
                'term_months' => 40,
                'interest_rate' => 12,
                'balloon_payment' => $template->cuota_balon ?? 0,
                'funding' => $template->bono_bpp ?? 0,
                'bpp' => $template->bono_bpp ?? 0,
                'bfh' => 0,
                'initial_quota' => $template->cuota_inicial ?? 0,
                'currency' => 'USD',
                'status' => 'vigente'
            ];
            
            $this->info('Datos del contrato preparados:');
            foreach ($contractData as $key => $value) {
                $this->info("  {$key}: {$value}");
            }
            
            // Intentar crear el contrato
            $this->info('\nIntentando crear contrato...');
            
            DB::beginTransaction();
            
            try {
                $contract = Contract::create($contractData);
                
                if ($contract) {
                    $this->info('✅ CONTRATO CREADO EXITOSAMENTE!');
                    $this->info('Contract ID: ' . $contract->contract_id);
                    DB::commit();
                } else {
                    $this->error('❌ Contract::create() retornó null');
                    DB::rollback();
                }
                
            } catch (\Exception $e) {
                $this->error('❌ EXCEPCIÓN AL CREAR CONTRATO:');
                $this->error('Mensaje: ' . $e->getMessage());
                $this->error('Archivo: ' . $e->getFile());
                $this->error('Línea: ' . $e->getLine());
                $this->error('Stack trace: ' . $e->getTraceAsString());
                DB::rollback();
            }
            
        } catch (\Exception $e) {
            $this->error('Error general: ' . $e->getMessage());
        }
    }
}