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
        Schema::table('lots', function (Blueprint $table) {
            // Campos para rastrear la sincronización con LOGICWARE API
            $table->string('external_id', 100)->nullable()->after('status')->comment('ID del lote en LOGICWARE');
            $table->string('external_code', 50)->nullable()->after('external_id')->comment('Código original del lote (Ej: E2-02)');
            $table->timestamp('external_sync_at')->nullable()->after('external_code')->comment('Última sincronización con API externa');
            $table->json('external_data')->nullable()->after('external_sync_at')->comment('Datos adicionales del API externa');
            
            // Índice para búsqueda por código externo
            $table->index('external_code', 'idx_lots_external_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex('idx_lots_external_code');
            $table->dropColumn(['external_id', 'external_code', 'external_sync_at', 'external_data']);
        });
    }
};
