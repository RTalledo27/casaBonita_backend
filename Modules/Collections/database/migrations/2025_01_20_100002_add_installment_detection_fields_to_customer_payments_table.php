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
        // Verificar si la tabla customer_payments existe antes de modificarla
        if (!Schema::hasTable('customer_payments')) {
            return;
        }
        
        Schema::table('customer_payments', function (Blueprint $table) {
            // Campos para detección de cuotas
            $table->enum('installment_type', ['first', 'second', 'regular', 'unknown'])
                  ->default('unknown')
                  ->after('notes')
                  ->comment('Tipo de cuota detectada');
            
            $table->enum('installment_detection_method', ['automatic', 'manual'])
                  ->default('automatic')
                  ->after('installment_type')
                  ->comment('Método de detección de la cuota');
            
            $table->boolean('affects_commissions')
                  ->default(false)
                  ->after('installment_detection_method')
                  ->comment('Indica si este pago afecta comisiones');
            
            $table->boolean('commission_event_dispatched')
                  ->default(false)
                  ->after('affects_commissions')
                  ->comment('Indica si ya se disparó el evento de comisión');
            
            $table->uuid('commission_event_id')
                  ->nullable()
                  ->after('commission_event_dispatched')
                  ->comment('ID del evento de comisión disparado');
            
            // Campos adicionales para tracking
            $table->timestamp('installment_detected_at')
                  ->nullable()
                  ->after('commission_event_id')
                  ->comment('Fecha cuando se detectó el tipo de cuota');
            
            $table->unsignedBigInteger('installment_detected_by')
                  ->nullable()
                  ->after('installment_detected_at')
                  ->comment('Usuario que detectó/confirmó el tipo de cuota');
            
            $table->text('installment_detection_notes')
                  ->nullable()
                  ->after('installment_detected_by')
                  ->comment('Notas sobre la detección de cuota');
            
            // Índices para mejorar rendimiento
            $table->index(['installment_type']);
            $table->index(['affects_commissions']);
            $table->index(['commission_event_dispatched']);
            $table->index(['commission_event_id']);
            $table->index(['installment_detected_at']);
            
            // Índices compuestos para búsquedas frecuentes
            $table->index(['ar_id', 'installment_type']);
            $table->index(['client_id', 'installment_type']);
            $table->index(['affects_commissions', 'commission_event_dispatched']);
            
            // Clave foránea para el usuario que detectó
            $table->foreign('installment_detected_by')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar si la tabla customer_payments existe antes de modificarla
        if (!Schema::hasTable('customer_payments')) {
            return;
        }
        
        Schema::table('customer_payments', function (Blueprint $table) {
            // Eliminar clave foránea
            $table->dropForeign(['installment_detected_by']);
            
            // Eliminar índices
            $table->dropIndex(['installment_type']);
            $table->dropIndex(['affects_commissions']);
            $table->dropIndex(['commission_event_dispatched']);
            $table->dropIndex(['commission_event_id']);
            $table->dropIndex(['installment_detected_at']);
            $table->dropIndex(['ar_id', 'installment_type']);
            $table->dropIndex(['client_id', 'installment_type']);
            $table->dropIndex(['affects_commissions', 'commission_event_dispatched']);
            
            // Eliminar campos agregados
            $table->dropColumn([
                'installment_type',
                'installment_detection_method',
                'affects_commissions',
                'commission_event_dispatched',
                'commission_event_id',
                'installment_detected_at',
                'installment_detected_by',
                'installment_detection_notes'
            ]);
        });
    }
};