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
        Schema::table('commissions', function (Blueprint $table) {
            // Campos de trazabilidad para comisiones
            $table->string('financial_source')->default('contract_direct')->after('payment_percentage')
                  ->comment('Fuente de datos financieros: lot_financial_template, contract_direct');
            $table->unsignedBigInteger('template_version_id')->nullable()->after('financial_source')
                  ->comment('ID del template financiero usado para el cálculo');
            $table->timestamp('calculation_date')->nullable()->after('template_version_id')
                  ->comment('Fecha y hora del cálculo de la comisión');
            
            // Índices para optimizar consultas
            $table->index('financial_source');
            $table->index('template_version_id');
            $table->index('calculation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex(['financial_source']);
            $table->dropIndex(['template_version_id']);
            $table->dropIndex(['calculation_date']);
            
            // Eliminar columnas
            $table->dropColumn(['financial_source', 'template_version_id', 'calculation_date']);
        });
    }
};
