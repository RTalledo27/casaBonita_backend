<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Campo para identificar la fuente del contrato
            $table->string('source', 50)->nullable()->after('contract_number')
                ->comment('Fuente del contrato: manual, logicware, etc.');
            
            // Campo para guardar los datos completos de Logicware
            $table->json('logicware_data')->nullable()->after('source')
                ->comment('Datos completos del documento de Logicware (JSON)');
            
            // Índice para búsquedas rápidas por fuente
            $table->index('source', 'contracts_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_source_index');
            $table->dropColumn(['source', 'logicware_data']);
        });
    }
};
