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
            
            // Índices para mejorar rendimiento
            $table->index(['payment_dependency_type']);
            $table->index(['payment_verification_status']);
            $table->index(['next_verification_date']);
            $table->index(['auto_verification_enabled']);
            $table->index(['last_payment_event_id']);
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
            // Eliminar índices solo si existen
            if (Schema::hasColumn('commissions', 'payment_dependency_type')) {
                try {
                    $table->dropIndex('commissions_payment_dependency_type_index');
                } catch (Exception $e) {
                    // Índice no existe, continuar
                }
            }
            
            if (Schema::hasColumn('commissions', 'payment_verification_status')) {
                try {
                    $table->dropIndex('commissions_payment_verification_status_index');
                } catch (Exception $e) {
                    // Índice no existe, continuar
                }
            }
            
            if (Schema::hasColumn('commissions', 'next_verification_date')) {
                try {
                    $table->dropIndex('commissions_next_verification_date_index');
                } catch (Exception $e) {
                    // Índice no existe, continuar
                }
            }
            
            if (Schema::hasColumn('commissions', 'auto_verification_enabled')) {
                try {
                    $table->dropIndex('commissions_auto_verification_enabled_index');
                } catch (Exception $e) {
                    // Índice no existe, continuar
                }
            }
            
            if (Schema::hasColumn('commissions', 'last_payment_event_id')) {
                try {
                    $table->dropIndex('commissions_last_payment_event_id_index');
                } catch (Exception $e) {
                    // Índice no existe, continuar
                }
            }
            
            // Eliminar campos agregados solo si existen
            $columnsToRemove = [];
            
            if (Schema::hasColumn('commissions', 'payment_dependency_type')) {
                $columnsToRemove[] = 'payment_dependency_type';
            }
            if (Schema::hasColumn('commissions', 'required_client_payments')) {
                $columnsToRemove[] = 'required_client_payments';
            }
            if (Schema::hasColumn('commissions', 'client_payments_verified')) {
                $columnsToRemove[] = 'client_payments_verified';
            }
            if (Schema::hasColumn('commissions', 'payment_verification_status')) {
                $columnsToRemove[] = 'payment_verification_status';
            }
            if (Schema::hasColumn('commissions', 'next_verification_date')) {
                $columnsToRemove[] = 'next_verification_date';
            }
            if (Schema::hasColumn('commissions', 'verification_notes')) {
                $columnsToRemove[] = 'verification_notes';
            }
            if (Schema::hasColumn('commissions', 'last_payment_event_id')) {
                $columnsToRemove[] = 'last_payment_event_id';
            }
            if (Schema::hasColumn('commissions', 'auto_verification_enabled')) {
                $columnsToRemove[] = 'auto_verification_enabled';
            }
            if (Schema::hasColumn('commissions', 'verification_retry_count')) {
                $columnsToRemove[] = 'verification_retry_count';
            }
            if (Schema::hasColumn('commissions', 'last_verification_attempt')) {
                $columnsToRemove[] = 'last_verification_attempt';
            }
            
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });
    }
};