<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primero, cambiar temporalmente la columna a VARCHAR para permitir la migración
        DB::statement("ALTER TABLE employees MODIFY COLUMN employee_type VARCHAR(255)");
        
        // Actualizar los valores existentes para que coincidan con los nuevos valores del enum
        DB::statement("UPDATE employees SET employee_type = 'asesor_inmobiliario' WHERE employee_type = 'vendedor'");
        DB::statement("UPDATE employees SET employee_type = 'asesor_inmobiliario' WHERE employee_type = 'administrativo'");
        DB::statement("UPDATE employees SET employee_type = 'asesor_inmobiliario' WHERE employee_type = 'gerente'");
        DB::statement("UPDATE employees SET employee_type = 'jefa_de_ventas' WHERE employee_type = 'jefe_ventas'");
        
        // Ahora cambiar la columna al nuevo enum con todos los valores
        DB::statement("ALTER TABLE employees MODIFY COLUMN employee_type ENUM(
            'asesor_inmobiliario',
            'jefa_de_ventas',
            'arquitecto',
            'arquitecta',
            'community_manager_corporativo',
            'ingeniero_de_sistemas',
            'diseñador_audiovisual_area_de_marketing',
            'encargado_de_ti',
            'director',
            'analista_de_administracion',
            'tracker',
            'contadora_junior'
        ) NOT NULL DEFAULT 'asesor_inmobiliario'");
    }

    public function down(): void
    {
        // Restaurar los valores originales del enum
        DB::statement("ALTER TABLE employees MODIFY COLUMN employee_type VARCHAR(255)");
        
        // Mapear de vuelta a los valores originales
        DB::statement("UPDATE employees SET employee_type = 'vendedor' WHERE employee_type IN ('arquitecto', 'arquitecta', 'community_manager_corporativo', 'ingeniero_de_sistemas', 'diseñador_audiovisual_area_de_marketing', 'encargado_de_ti', 'director', 'analista_de_administracion', 'tracker', 'contadora_junior')");
        DB::statement("UPDATE employees SET employee_type = 'jefe_ventas' WHERE employee_type = 'jefa_de_ventas'");
        
        // Restaurar el enum original
        DB::statement("ALTER TABLE employees MODIFY COLUMN employee_type ENUM(
            'asesor_inmobiliario',
            'vendedor',
            'administrativo',
            'gerente',
            'jefe_ventas'
        ) NOT NULL");
    }
};