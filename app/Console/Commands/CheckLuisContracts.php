<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\app\Models\Contract;
use Modules\Sales\app\Models\Reservation;
use Modules\Sales\app\Models\Client;
use Modules\HR\app\Models\Employee;

class CheckLuisContracts extends Command
{
    protected $signature = 'check:luis-contracts';
    protected $description = 'Verificar contratos de LUIS TAVARA para análisis de duplicación';

    public function handle()
    {
        $this->info('=== ANÁLISIS DE CONTRATOS DE LUIS TAVARA ===');
        $this->newLine();
        
        try {
            // Buscar contratos por cliente con nombre similar a Luis Tavara
            $contracts = Contract::whereHas('reservation.client', function($q) {
                $q->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%luis%tavara%'])
                  ->orWhereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%tavara%luis%']);
            })->with(['reservation.client', 'reservation.lot'])->get();
            
            $this->info('Contratos encontrados para LUIS TAVARA como cliente: ' . $contracts->count());
            $this->newLine();
            
            if ($contracts->count() > 0) {
                $this->info('DETALLE DE CONTRATOS COMO CLIENTE:');
                $this->info('===================================');
                
                foreach($contracts as $contract) {
                    $client = $contract->reservation->client;
                    $lot = $contract->reservation->lot;
                    
                    $this->line('ID Contrato: ' . $contract->contract_id);
                    $this->line('Número: ' . ($contract->contract_number ?? 'N/A'));
                    $this->line('Cliente: ' . $client->first_name . ' ' . $client->last_name);
                    $this->line('Email: ' . ($client->email ?? 'N/A'));
                    $this->line('Lote: ' . ($lot->num_lot ?? 'N/A'));
                    $this->line('Fecha firma: ' . ($contract->sign_date ?? 'N/A'));
                    $this->line('Precio total: ' . ($contract->total_price ?? 'N/A'));
                    $this->line('---');
                }
            }
            
            // También buscar por asesor
            $this->newLine();
            $this->info('=== BÚSQUEDA POR ASESOR ===');
            
            $contractsByAdvisor = Contract::whereHas('reservation.advisor.user', function($q) {
                $q->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%luis%tavara%'])
                  ->orWhere('email', 'LIKE', '%luis.tavara%');
            })->with(['reservation.client', 'reservation.lot', 'reservation.advisor.user'])->get();
            
            $this->info('Contratos donde LUIS TAVARA es el asesor: ' . $contractsByAdvisor->count());
            $this->newLine();
            
            if ($contractsByAdvisor->count() > 0) {
                $this->info('DETALLE DE CONTRATOS COMO ASESOR:');
                $this->info('=================================');
                
                foreach($contractsByAdvisor as $contract) {
                    $client = $contract->reservation->client;
                    $lot = $contract->reservation->lot;
                    $advisor = $contract->reservation->advisor;
                    
                    $this->line('ID Contrato: ' . $contract->contract_id);
                    $this->line('Número: ' . ($contract->contract_number ?? 'N/A'));
                    $this->line('Cliente: ' . $client->first_name . ' ' . $client->last_name);
                    $this->line('Asesor: ' . ($advisor->user ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'N/A'));
                    $this->line('Email Asesor: ' . ($advisor->user->email ?? 'N/A'));
                    $this->line('Lote: ' . ($lot->num_lot ?? 'N/A'));
                    $this->line('Fecha firma: ' . ($contract->sign_date ?? 'N/A'));
                    $this->line('---');
                }
            }
            
            // Buscar también reservaciones sin contrato
            $this->newLine();
            $this->info('=== RESERVACIONES SIN CONTRATO ===');
            
            $reservationsWithoutContract = Reservation::whereDoesntHave('contract')
                ->whereHas('advisor.user', function($q) {
                    $q->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%luis%tavara%'])
                      ->orWhere('email', 'LIKE', '%luis.tavara%');
                })->with(['client', 'lot', 'advisor.user'])->get();
            
            $this->info('Reservaciones de LUIS TAVARA sin contrato: ' . $reservationsWithoutContract->count());
            
            if ($reservationsWithoutContract->count() > 0) {
                foreach($reservationsWithoutContract as $reservation) {
                    $client = $reservation->client;
                    $lot = $reservation->lot;
                    $advisor = $reservation->advisor;
                    
                    $this->line('ID Reservación: ' . $reservation->reservation_id);
                    $this->line('Cliente: ' . $client->first_name . ' ' . $client->last_name);
                    $this->line('Asesor: ' . ($advisor->user ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'N/A'));
                    $this->line('Lote: ' . ($lot->num_lot ?? 'N/A'));
                    $this->line('Fecha: ' . ($reservation->reservation_date ?? 'N/A'));
                    $this->line('---');
                }
            }
            
            // Resumen final
            $this->newLine();
            $this->info('=== RESUMEN ===');
            $this->info('Total contratos como cliente: ' . $contracts->count());
            $this->info('Total contratos como asesor: ' . $contractsByAdvisor->count());
            $this->info('Total reservaciones sin contrato: ' . $reservationsWithoutContract->count());
            $this->info('Total general: ' . ($contracts->count() + $contractsByAdvisor->count() + $reservationsWithoutContract->count()));
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
        
        return 0;
    }
}