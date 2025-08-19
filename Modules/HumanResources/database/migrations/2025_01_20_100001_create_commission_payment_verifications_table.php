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
        Schema::create('commission_payment_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('commission_id');
            $table->unsignedBigInteger('client_payment_id')->nullable();
            $table->unsignedBigInteger('account_receivable_id')->nullable();
            
            $table->enum('payment_installment', ['first', 'second'])
                  ->comment('Tipo de cuota verificada');
            
            $table->timestamp('verification_date')
                  ->useCurrent()
                  ->comment('Fecha de verificación');
            
            $table->decimal('verified_amount', 15, 2)
                  ->comment('Monto verificado del pago');
            
            $table->enum('verification_status', ['verified', 'pending', 'failed', 'reversed'])
                  ->default('verified')
                  ->comment('Estado de la verificación');
            
            $table->enum('verification_method', ['automatic', 'manual'])
                  ->default('automatic')
                  ->comment('Método de verificación');
            
            $table->unsignedBigInteger('verified_by')
                  ->nullable()
                  ->comment('Usuario que verificó (para verificaciones manuales)');
            
            $table->unsignedBigInteger('reversed_by')
                  ->nullable()
                  ->comment('Usuario que revirtió la verificación');
            
            $table->text('reversal_reason')
                  ->nullable()
                  ->comment('Razón de la reversión');
            
            $table->uuid('event_id')
                  ->nullable()
                  ->comment('ID del evento que disparó la verificación');
            
            $table->text('notes')
                  ->nullable()
                  ->comment('Notas adicionales');
            
            $table->timestamps();
            
            // Claves foráneas - solo si las tablas existen
            if (Schema::hasTable('commissions')) {
                $table->foreign('commission_id')
                      ->references('commission_id')
                      ->on('commissions')
                      ->onDelete('cascade');
            }
            
            if (Schema::hasTable('customer_payments')) {
                $table->foreign('client_payment_id')
                      ->references('payment_id')
                      ->on('customer_payments')
                      ->onDelete('set null');
            }
            
            if (Schema::hasTable('accounts_receivable')) {
                $table->foreign('account_receivable_id')
                      ->references('ar_id')
                      ->on('accounts_receivable')
                      ->onDelete('set null');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('verified_by')
                      ->references('user_id')
                      ->on('users')
                      ->onDelete('set null');
                
                $table->foreign('reversed_by')
                      ->references('user_id')
                      ->on('users')
                      ->onDelete('set null');
            }
            
            // Índices para mejorar rendimiento
            $table->index(['commission_id']);
            $table->index(['payment_installment']);
            $table->index(['verification_status']);
            $table->index(['event_id']);
            $table->index(['verification_date']);
            $table->index(['client_payment_id']);
            $table->index(['account_receivable_id']);
            
            // Índice compuesto para búsquedas frecuentes (con nombres cortos)
            $table->index(['commission_id', 'payment_installment'], 'cpv_commission_installment_idx');
            $table->index(['commission_id', 'verification_status'], 'cpv_commission_status_idx');
            
            // Constraint único para evitar verificaciones duplicadas
            $table->unique(['commission_id', 'payment_installment'], 'unique_commission_installment_verification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payment_verifications');
    }
};