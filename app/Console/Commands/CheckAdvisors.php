<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Models\Employee;

class CheckAdvisors extends Command
{
    protected $signature = 'check:advisors';
    protected $description = 'Check advisors in database';

    public function handle()
    {
        $this->info('=== ASESORES EN LA BASE DE DATOS ===');
        
        $advisors = Employee::with('user')
            ->where('employee_type', 'asesor_inmobiliario')
            ->get();
            
        $this->info('Total de asesores: ' . $advisors->count());
        $this->info('');
        
        foreach ($advisors as $advisor) {
            $userName = $advisor->user ? 
                $advisor->user->first_name . ' ' . $advisor->user->last_name : 
                'Sin usuario';
                
            $this->info(sprintf(
                'ID: %s | Código: %s | Nombre: %s | Estado: %s',
                $advisor->employee_id,
                $advisor->employee_code ?? 'N/A',
                $userName,
                $advisor->employment_status ?? 'N/A'
            ));
        }
        
        $this->info('');
        $this->info('=== BÚSQUEDA ESPECÍFICA ===');
        
        // Buscar nombres específicos que están fallando
        $searchNames = ['PAOLA JUDITH CANDELA NEIRA', 'DANIELA MERINO'];
        
        foreach ($searchNames as $searchName) {
            $this->info("Buscando: {$searchName}");
            
            $found = Employee::with('user')
                ->where('employee_type', 'asesor_inmobiliario')
                ->whereHas('user', function($q) use ($searchName) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $searchName . '%'])
                      ->orWhere('first_name', 'LIKE', '%' . $searchName . '%')
                      ->orWhere('last_name', 'LIKE', '%' . $searchName . '%');
                })->first();
                
            if ($found) {
                $this->info("  ✓ Encontrado: ID {$found->employee_id} - {$found->user->first_name} {$found->user->last_name}");
            } else {
                $this->error("  ✗ No encontrado: {$searchName}");
            }
        }
        
        return 0;
    }
}