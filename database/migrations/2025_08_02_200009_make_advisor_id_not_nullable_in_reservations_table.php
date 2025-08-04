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
        
        // Luego, modificar la columna para que no sea nullable
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('advisor_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('advisor_id')->nullable()->change();
        });
    }
};
