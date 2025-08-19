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
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->enum('event_type', [
                'client_payment_received', 
                'installment_paid', 
                'commission_verification_requested'
            ])->comment('Tipo de evento de pago');
            
            $table->unsignedBigInteger('payment_id')
                  ->comment('ID del pago que disparó el evento');
            
            $table->unsignedBigInteger('contract_id')
                  ->nullable()
                  ->comment('ID del contrato relacionado');
            
            $table->enum('installment_type', ['first', 'second', 'regular'])
                  ->nullable()
                  ->comment('Tipo de cuota si aplica');
            
            $table->json('event_data')
                  ->nullable()
                  ->comment('Datos adicionales del evento en formato JSON');
            
            $table->boolean('processed')
                  ->default(false)
                  ->comment('Indica si el evento fue procesado');
            
            $table->timestamp('processed_at')
                  ->nullable()
                  ->comment('Fecha cuando se procesó el evento');
            
            $table->integer('retry_count')
                  ->default(0)
                  ->comment('Número de reintentos de procesamiento');
            
            $table->timestamp('last_retry_at')
                  ->nullable()
                  ->comment('Fecha del último reintento');
            
            $table->text('error_message')
                  ->nullable()
                  ->comment('Mensaje de error si el procesamiento falló');
            
            $table->unsignedBigInteger('triggered_by')
                  ->nullable()
                  ->comment('Usuario que disparó el evento');
            
            $table->timestamp('created_at')
                  ->useCurrent()
                  ->comment('Fecha de creación del evento');
            
            // Claves foráneas - solo si las tablas existen
            if (Schema::hasTable('customer_payments')) {
                $table->foreign('payment_id')
                      ->references('payment_id')
                      ->on('customer_payments')
                      ->onDelete('cascade');
            }
            
            if (Schema::hasTable('contracts')) {
                $table->foreign('contract_id')
                      ->references('contract_id')
                      ->on('contracts')
                      ->onDelete('cascade');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('triggered_by')
                      ->references('user_id')
                      ->on('users')
                      ->onDelete('set null');
            }
            
            // Índices para mejorar rendimiento
            $table->index(['event_type']);
            $table->index(['processed']);
            $table->index(['contract_id']);
            $table->index(['payment_id']);
            $table->index(['installment_type']);
            $table->index(['created_at']);
            $table->index(['processed_at']);
            $table->index(['retry_count']);
            
            // Índices compuestos para búsquedas frecuentes
            $table->index(['event_type', 'processed']);
            $table->index(['contract_id', 'installment_type']);
            $table->index(['processed', 'retry_count']);
            $table->index(['payment_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};