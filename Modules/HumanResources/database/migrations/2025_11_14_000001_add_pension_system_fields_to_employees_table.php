<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agregar campos del sistema pensionario a la tabla employees
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // NOTA: pension_system, afp_provider y cuspp YA EXISTEN
            // Solo agregamos los campos faltantes
            
            // Verificar si la columna existe antes de agregarla
            if (!Schema::hasColumn('employees', 'has_family_allowance')) {
                $table->boolean('has_family_allowance')
                    ->default(false)
                    ->after('cuspp')
                    ->comment('Si recibe asignación familiar (S/ 102.50)');
            }
            
            if (!Schema::hasColumn('employees', 'number_of_children')) {
                $table->integer('number_of_children')
                    ->default(0)
                    ->after('has_family_allowance')
                    ->comment('Número de hijos menores de 18 años');
            }
            
            // Campos organizacionales (opcionales)
            if (!Schema::hasColumn('employees', 'department')) {
                $table->string('department', 100)
                    ->nullable()
                    ->after('number_of_children')
                    ->comment('Departamento del empleado');
            }
            
            if (!Schema::hasColumn('employees', 'position')) {
                $table->string('position', 100)
                    ->nullable()
                    ->after('department')
                    ->comment('Cargo o posición del empleado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Solo eliminar las columnas que agregamos (NO las que ya existían)
            if (Schema::hasColumn('employees', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('employees', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('employees', 'number_of_children')) {
                $table->dropColumn('number_of_children');
            }
            if (Schema::hasColumn('employees', 'has_family_allowance')) {
                $table->dropColumn('has_family_allowance');
            }
        });
    }
};
