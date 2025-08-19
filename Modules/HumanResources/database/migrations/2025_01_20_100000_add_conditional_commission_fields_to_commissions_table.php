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
        // Verificar si la tabla commissions existe antes de modificarla
        if (!Schema::hasTable('commissions')) {
            return;
        }
        
        Schema::table('commissions', function (Blueprint $table) {
            // Campos para comisiones condicionadas
            $table->enum('payment_dependency_type', ['none', 'client_installments'])
                  ->default('none')
                  ->after('payment_part');
            
            $table->integer('required_client_payments')
                  ->default(0)
                  ->after('payment_dependency_type');
            
            $table->integer('client_payments_verified')
                  ->default(0)
                  ->after('required_client_payments');
            
            $table->enum('payment_verification_status', [
                'pending_verification', 
                'first_payment_verified', 
                'second_payment_verified', 
                'fully_verified'
            ])->default('pending_verification')
              ->after('client_payments_verified');
            
            $table->date('next_verification_date')
                  ->nullable()
                  ->after('payment_verification_status');
            
            $table->text('verification_notes')
                  ->nullable()
                  ->after('next_verification_date');
            
            // Nuevos campos para eventos y tracking
            $table->uuid('last_payment_event_id')
                  ->nullable()
                  ->after('verification_notes');
            
            $table->boolean('auto_verification_enabled')
                  ->default(true)
                  ->after('last_payment_event_id');
            
            $table->integer('verification_retry_count')
                  ->default(0)
                  ->after('auto_verification_enabled');
            
            $table->timestamp('last_verification_attempt')
                  ->nullable()
                  ->after('verification_retry_count');
            
            // Actualizar enum de payment_status para incluir nuevos estados
            $table->enum('payment_status_new', [
                'pending', 
                'pending_client_payment', 
                'first_payment_eligible', 
                'second_payment_eligible', 
                'paid', 
                'partially_paid',
                'cancelled',
                'verification_failed',
                'auto_verification_disabled'
            ])->default('pending')->after('last_verification_attempt');
            
            // Índices para mejorar rendimiento
            $table->index(['payment_dependency_type']);
            $table->index(['payment_verification_status']);
            $table->index(['next_verification_date']);
            $table->index(['auto_verification_enabled']);
            $table->index(['last_payment_event_id']);
        });
        
        // Migrar datos existentes del campo payment_status al nuevo campo
        DB::statement("UPDATE commissions SET payment_status_new = CASE 
            WHEN payment_status = 'pendiente' THEN 'pending'
            WHEN payment_status = 'pagado' THEN 'paid'
            WHEN payment_status = 'cancelado' THEN 'cancelled'
            ELSE 'pending'
        END");
        
        // Eliminar el campo anterior y renombrar el nuevo
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
        
        Schema::table('commissions', function (Blueprint $table) {
            $table->renameColumn('payment_status_new', 'payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar si la tabla commissions existe antes de modificarla
        if (!Schema::hasTable('commissions')) {
            return;
        }
        
        Schema::table('commissions', function (Blueprint $table) {
            // Restaurar enum original de payment_status
            $table->enum('payment_status_old', ['pendiente', 'pagado', 'cancelado'])
                  ->default('pendiente')
                  ->after('commission_amount');
        });
        
        // Migrar datos de vuelta
        DB::statement("UPDATE commissions SET payment_status_old = CASE 
            WHEN payment_status = 'pending' THEN 'pendiente'
            WHEN payment_status = 'paid' THEN 'pagado'
            WHEN payment_status = 'cancelled' THEN 'cancelado'
            ELSE 'pendiente'
        END");
        
        Schema::table('commissions', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex(['payment_dependency_type']);
            $table->dropIndex(['payment_verification_status']);
            $table->dropIndex(['next_verification_date']);
            $table->dropIndex(['auto_verification_enabled']);
            $table->dropIndex(['last_payment_event_id']);
            
            // Eliminar campos agregados
            $table->dropColumn([
                'payment_dependency_type',
                'required_client_payments',
                'client_payments_verified',
                'payment_verification_status',
                'next_verification_date',
                'verification_notes',
                'last_payment_event_id',
                'auto_verification_enabled',
                'verification_retry_count',
                'last_verification_attempt',
                'payment_status'
            ]);
        });
        
        Schema::table('commissions', function (Blueprint $table) {
            $table->renameColumn('payment_status_old', 'payment_status');
        });
    }
};