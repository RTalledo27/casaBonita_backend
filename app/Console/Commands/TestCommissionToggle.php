<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Repositories\CommissionRepository;

class TestCommissionToggle extends Command
{
    protected $signature = 'test:commission-toggle';
    protected $description = 'Test commission toggle functionality';

    public function handle()
    {
        $this->info('=== PRUEBA DEL TOGGLE MOSTRAR DIVISIONES ===');
        
        $repo = app(CommissionRepository::class);
        
        // Test 1: Toggle OFF (solo comisiones padre)
        $this->info('\n1. TOGGLE DESACTIVADO (include_split_payments = false):');
        $commissionsOff = $repo->getAll(['include_split_payments' => false]);
        $parentCount = $commissionsOff->where('is_payable', false)->count();
        $childCount = $commissionsOff->where('is_payable', true)->count();
        
        $this->line("   Total: {$commissionsOff->count()}");
        $this->line("   Padre (is_payable=false): {$parentCount}");
        $this->line("   Hijas (is_payable=true): {$childCount}");
        
        if ($childCount > 0) {
            $this->error('   ❌ ERROR: Se muestran comisiones hijas cuando toggle está OFF');
        } else {
            $this->info('   ✅ CORRECTO: Solo comisiones padre');
        }
        
        // Test 2: Toggle ON (todas las comisiones)
        $this->info('\n2. TOGGLE ACTIVADO (include_split_payments = true):');
        $commissionsOn = $repo->getAll(['include_split_payments' => true]);
        $parentCountOn = $commissionsOn->where('is_payable', false)->count();
        $childCountOn = $commissionsOn->where('is_payable', true)->count();
        
        $this->line("   Total: {$commissionsOn->count()}");
        $this->line("   Padre (is_payable=false): {$parentCountOn}");
        $this->line("   Hijas (is_payable=true): {$childCountOn}");
        
        if ($childCountOn > 0) {
            $this->info('   ✅ CORRECTO: Se muestran padre e hijas');
        } else {
            $this->warn('   ⚠️  No hay comisiones hijas en el sistema');
        }
        
        // Test 3: Sin filtro
        $this->info('\n3. SIN FILTRO (comportamiento por defecto):');
        $commissionsDefault = $repo->getAll([]);
        $parentCountDefault = $commissionsDefault->where('is_payable', false)->count();
        $childCountDefault = $commissionsDefault->where('is_payable', true)->count();
        
        $this->line("   Total: {$commissionsDefault->count()}");
        $this->line("   Padre (is_payable=false): {$parentCountDefault}");
        $this->line("   Hijas (is_payable=true): {$childCountDefault}");
        
        $this->info('\n=== PRUEBA COMPLETADA ===');
        
        return 0;
    }
}