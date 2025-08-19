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
        Schema::table('lot_financial_templates', function (Blueprint $table) {
            // Modify decimal columns to accommodate larger values
            $table->decimal('descuento', 12, 2)->change();
            $table->decimal('cuota_balon', 12, 2)->change();
            $table->decimal('bono_bpp', 12, 2)->change();
            $table->decimal('cuota_inicial', 12, 2)->change();
            $table->decimal('ci_fraccionamiento', 12, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lot_financial_templates', function (Blueprint $table) {
            // Revert decimal columns to original size
            $table->decimal('descuento', 5, 2)->change();
            $table->decimal('cuota_balon', 5, 2)->change();
            $table->decimal('bono_bpp', 5, 2)->change();
            $table->decimal('cuota_inicial', 5, 2)->change();
            $table->decimal('ci_fraccionamiento', 5, 2)->change();
        });
    }
};
