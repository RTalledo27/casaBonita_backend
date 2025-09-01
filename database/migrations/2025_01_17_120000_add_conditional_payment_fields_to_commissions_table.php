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
        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                // Verificar si las columnas no existen ya
                if (!Schema::hasColumn('commissions', 'requires_client_payment_verification')) {
                    // Campos para comisiones condicionadas a pagos del cliente
                    $table->boolean('requires_client_payment_verification')->default(false)->after('sales_count');
                }
                if (!Schema::hasColumn('commissions', 'payment_verification_status')) {
                    $table->enum('payment_verification_status', [
                        'pending_verification',
                        'first_payment_verified', 
                        'second_payment_verified',
                        'fully_verified',
                        'verification_failed'
                    ])->default('pending_verification')->after('requires_client_payment_verification');
                }
                if (!Schema::hasColumn('commissions', 'first_payment_verified_at')) {
                    $table->timestamp('first_payment_verified_at')->nullable()->after('payment_verification_status');
                }
                if (!Schema::hasColumn('commissions', 'second_payment_verified_at')) {
                    $table->timestamp('second_payment_verified_at')->nullable()->after('first_payment_verified_at');
                }
                if (!Schema::hasColumn('commissions', 'is_eligible_for_payment')) {
                    $table->boolean('is_eligible_for_payment')->default(true)->after('second_payment_verified_at');
                }
                if (!Schema::hasColumn('commissions', 'verification_notes')) {
                    $table->text('verification_notes')->nullable()->after('is_eligible_for_payment');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                $columnsToCheck = [
                    'requires_client_payment_verification',
                    'payment_verification_status',
                    'first_payment_verified_at',
                    'second_payment_verified_at',
                    'is_eligible_for_payment',
                    'verification_notes'
                ];
                
                $columnsToRemove = [];
                foreach ($columnsToCheck as $column) {
                    if (Schema::hasColumn('commissions', $column)) {
                        $columnsToRemove[] = $column;
                    }
                }
                
                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }
    }
};