<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_financial_templates', function (Blueprint $table) {
            $table->decimal('bono_techo_propio', 12, 2)->nullable()->default(0)->after('bono_bpp');
            $table->decimal('precio_total_real', 14, 2)->nullable()->default(0)->after('bono_techo_propio');
        });

        // Calcular precio_total_real para registros existentes: precio_venta + bono_techo_propio
        // Por defecto el bono es 51250 para lotes tipo Techo Propio
        // Se deja en 0 por ahora y se actualizará con el siguiente sync de Logicware
    }

    public function down(): void
    {
        Schema::table('lot_financial_templates', function (Blueprint $table) {
            $table->dropColumn(['bono_techo_propio', 'precio_total_real']);
        });
    }
};
