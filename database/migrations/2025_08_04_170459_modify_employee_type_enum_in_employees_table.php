<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero, cambiar temporalmente la columna a VARCHAR para evitar problemas con ENUM
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_type_temp')->nullable();
        });
        
        // Copiar datos existentes
        DB::statement("UPDATE employees SET employee_type_temp = employee_type");
        
        // Eliminar la columna original
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
        
        // Crear la nueva columna con los nuevos valores del enum
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('employee_type', [
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
            ])->after('employee_code');
        });
        
        // Migrar datos existentes con mapeo
        DB::statement("
            UPDATE employees 
            SET employee_type = CASE 
                WHEN employee_type_temp = 'asesor_inmobiliario' THEN 'asesor_inmobiliario'
                WHEN employee_type_temp = 'vendedor' THEN 'asesor_inmobiliario'
                WHEN employee_type_temp = 'administrativo' THEN 'analista_de_administracion'
                WHEN employee_type_temp = 'gerente' THEN 'director'
                WHEN employee_type_temp = 'jefe_ventas' THEN 'jefa_de_ventas'
                ELSE 'asesor_inmobiliario'
            END
        ");
        
        // Eliminar la columna temporal
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employee_type_temp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Crear columna temporal
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_type_temp')->nullable();
        });
        
        // Copiar datos existentes
        DB::statement("UPDATE employees SET employee_type_temp = employee_type");
        
        // Eliminar la columna actual
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
        
        // Restaurar el enum original
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('employee_type', ['asesor_inmobiliario', 'vendedor', 'administrativo', 'gerente', 'jefe_ventas'])->after('employee_code');
        });
        
        // Migrar datos de vuelta
        DB::statement("
            UPDATE employees 
            SET employee_type = CASE 
                WHEN employee_type_temp IN ('asesor_inmobiliario') THEN 'asesor_inmobiliario'
                WHEN employee_type_temp = 'jefa_de_ventas' THEN 'jefe_ventas'
                WHEN employee_type_temp = 'director' THEN 'gerente'
                WHEN employee_type_temp IN ('analista_de_administracion', 'tracker', 'contadora_junior', 'community_manager_corporativo', 'encargado_de_ti') THEN 'administrativo'
                ELSE 'asesor_inmobiliario'
            END
        ");
        
        // Eliminar la columna temporal
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employee_type_temp');
        });
    }
};
