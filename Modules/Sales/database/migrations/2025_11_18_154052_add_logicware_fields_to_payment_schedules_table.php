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
        Schema::table('payment_schedules', function (Blueprint $table) {
            // ID del schedule detail en Logicware para sincronización
            $table->unsignedBigInteger('logicware_schedule_det_id')->nullable()->after('notes');
            
            // Monto pagado reportado por Logicware (puede diferir del monto total si es pago parcial)
            $table->decimal('logicware_paid_amount', 15, 2)->nullable()->after('logicware_schedule_det_id');
            
            // Índice para búsquedas rápidas por ID de Logicware
            $table->index('logicware_schedule_det_id', 'idx_logicware_schedule_det');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_logicware_schedule_det');
            $table->dropColumn(['logicware_schedule_det_id', 'logicware_paid_amount']);
        });
    }
};
