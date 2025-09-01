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
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('down_payment', 14, 2)->after('total_price')->comment('Monto del enganche');
            $table->decimal('financing_amount', 14, 2)->after('down_payment')->comment('Monto financiado');
            $table->decimal('interest_rate', 5, 4)->after('financing_amount')->comment('Tasa de interés anual (ej: 0.0850 = 8.5%)');
            $table->integer('term_months')->after('interest_rate')->comment('Plazo en meses');
            $table->decimal('monthly_payment', 14, 2)->after('term_months')->comment('Pago mensual base');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'down_payment',
                'financing_amount',
                'interest_rate',
                'term_months',
                'monthly_payment'
            ]);
        });
    }
};
