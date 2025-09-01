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
        Schema::table('commissions', function (Blueprint $table) {
            // Agregar campos para el sistema de pagos divididos
            $table->enum('payment_type', ['first_payment', 'second_payment', 'full_payment'])->default('full_payment')->after('commission_amount');
            $table->decimal('total_commission_amount', 10, 2)->nullable()->after('payment_type');
            $table->integer('sales_count')->nullable()->after('total_commission_amount');
        });
        
        // Eliminar la restricción única de contract_id usando SQL directo
        try {
            DB::statement('ALTER TABLE commissions DROP INDEX commissions_contract_id_unique');
        } catch (\Exception $e) {
            // La restricción puede no existir, continuar
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // Eliminar los campos agregados
            $table->dropColumn(['payment_type', 'total_commission_amount', 'sales_count']);
        });
        
        // Restaurar la restricción única de contract_id
        Schema::table('commissions', function (Blueprint $table) {
            $table->unique('contract_id');
        });
    }
};
