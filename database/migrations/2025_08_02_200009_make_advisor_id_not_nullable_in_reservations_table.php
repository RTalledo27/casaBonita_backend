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
        // Primero, asegurar que todos los registros existentes tengan un advisor_id válido
        DB::statement("
            UPDATE reservations 
            SET advisor_id = (
                SELECT employee_id 
                FROM employees 
                WHERE employee_type = 'asesor_inmobiliario' 
                LIMIT 1
            ) 
            WHERE advisor_id IS NULL
        ");
        
        // Eliminar la foreign key existente que tiene onDelete('set null')
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['advisor_id']);
        });
        
        // Modificar la columna para que no sea nullable
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('advisor_id')->nullable(false)->change();
        });
        
        // Recrear la foreign key con onDelete('cascade') para compatibilidad con NOT NULL
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('advisor_id')->references('employee_id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la foreign key con cascade
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['advisor_id']);
        });
        
        // Hacer la columna nullable nuevamente
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('advisor_id')->nullable()->change();
        });
        
        // Recrear la foreign key original con onDelete('set null')
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('advisor_id')->references('employee_id')->on('employees')->onDelete('set null');
        });
    }
};
