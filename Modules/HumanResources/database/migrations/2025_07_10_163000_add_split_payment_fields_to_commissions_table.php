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
            // Período de generación de la comisión (YYYY-MM)
            $table->string('commission_period', 7)->nullable()->after('period_year');
            
            // Período de pago de la comisión (YYYY-MM-P1, YYYY-MM-P2, etc.)
            $table->string('payment_period', 10)->nullable()->after('commission_period');
            
            // Porcentaje del pago (50.00 para 50%, 100.00 para pago completo)
            $table->decimal('payment_percentage', 5, 2)->default(100.00)->after('payment_period');
            
            // Estado mejorado de la comisión
            $table->enum('status', ['generated', 'partially_paid', 'fully_paid', 'cancelled'])
                  ->default('generated')
                  ->after('payment_percentage');
            
            // Referencia al pago padre (para pagos divididos)
            $table->unsignedBigInteger('parent_commission_id')->nullable()->after('status');
            $table->foreign('parent_commission_id')->references('commission_id')->on('commissions')->onDelete('cascade');
            
            // Número de parte del pago (1, 2, 3, etc.)
            $table->tinyInteger('payment_part')->default(1)->after('parent_commission_id');
            
            // Índices para mejorar rendimiento
            $table->index(['commission_period']);
            $table->index(['payment_period']);
            $table->index(['status']);
            $table->index(['parent_commission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['parent_commission_id']);
            $table->dropIndex(['commission_period']);
            $table->dropIndex(['payment_period']);
            $table->dropIndex(['status']);
            $table->dropIndex(['parent_commission_id']);
            $table->dropColumn([
                'commission_period',
                'payment_period', 
                'payment_percentage',
                'status',
                'parent_commission_id',
                'payment_part'
            ]);
        });
    }
};