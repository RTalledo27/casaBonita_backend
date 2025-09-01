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
        // Verificar si las columnas ya existen antes de crearlas
        $contractsColumns = Schema::getColumnListing('contracts');
        
        Schema::table('contracts', function (Blueprint $table) use ($contractsColumns) {
            // Agregar campos para contratos directos solo si no existen
            if (!in_array('client_id', $contractsColumns)) {
                $table->unsignedBigInteger('client_id')->nullable()->after('contract_id');
            }
            if (!in_array('lot_id', $contractsColumns)) {
                $table->unsignedBigInteger('lot_id')->nullable()->after('client_id');
            }
            
            // Hacer reservation_id nullable
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
        });
        
        // Crear claves foráneas solo si las columnas existen y no tienen FK ya
        Schema::table('contracts', function (Blueprint $table) use ($contractsColumns) {
            if (in_array('client_id', $contractsColumns) || !in_array('client_id', Schema::getColumnListing('contracts'))) {
                try {
                    $table->foreign('client_id')->references('client_id')->on('clients')->onDelete('cascade');
                } catch (\Exception $e) {
                    // FK ya existe, continuar
                }
            }
            if (in_array('lot_id', $contractsColumns) || !in_array('lot_id', Schema::getColumnListing('contracts'))) {
                try {
                    $table->foreign('lot_id')->references('lot_id')->on('lots')->onDelete('cascade');
                } catch (\Exception $e) {
                    // FK ya existe, continuar
                }
            }
        });
        
        // Agregar constraint de integridad: debe tener reservation_id O (client_id Y lot_id)
         DB::statement('ALTER TABLE contracts ADD CONSTRAINT chk_contract_source CHECK (
             (reservation_id IS NOT NULL AND client_id IS NULL AND lot_id IS NULL) OR
             (reservation_id IS NULL AND client_id IS NOT NULL AND lot_id IS NOT NULL)
         )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar constraint
        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS chk_contract_source');
        
        Schema::table('contracts', function (Blueprint $table) {
            
            // Eliminar foreign keys
            $table->dropForeign(['client_id']);
            $table->dropForeign(['lot_id']);
            
            // Eliminar campos
            $table->dropColumn(['client_id', 'lot_id']);
            
            // Restaurar reservation_id como NOT NULL
            $table->unsignedBigInteger('reservation_id')->nullable(false)->change();
        });
    }
};
