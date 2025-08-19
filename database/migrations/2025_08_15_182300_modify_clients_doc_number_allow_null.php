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
        // Primero, eliminar la restricción única existente
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['doc_number']);
        });
        
        // Modificar la columna para permitir NULL primero
        Schema::table('clients', function (Blueprint $table) {
            $table->string('doc_number', 20)->nullable()->change();
        });
        
        // Ahora actualizar todos los doc_number vacíos a NULL
        DB::statement("UPDATE clients SET doc_number = NULL WHERE doc_number = '' OR doc_number = '0'");
        
        // Recrear la restricción única
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('doc_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir los cambios: convertir NULL a cadena vacía y hacer la columna NOT NULL
        DB::statement("UPDATE clients SET doc_number = '' WHERE doc_number IS NULL");
        
        Schema::table('clients', function (Blueprint $table) {
            $table->string('doc_number', 20)->unique()->change();
        });
    }
};