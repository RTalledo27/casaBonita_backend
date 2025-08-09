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
        Schema::create('lot_financial_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots', 'lot_id')->onDelete('cascade');
            $table->decimal('precio_lista', 12, 2)->nullable();
            $table->decimal('descuento', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->decimal('precio_contado', 12, 2)->nullable();
            $table->decimal('cuota_balon', 12, 2)->nullable();
            $table->decimal('bono_bpp', 12, 2)->nullable();
            $table->decimal('cuota_inicial', 12, 2)->nullable();
            $table->decimal('ci_fraccionamiento', 12, 2)->nullable();
            $table->decimal('installments_24', 12, 2)->nullable();
            $table->decimal('installments_40', 12, 2)->nullable();
            $table->decimal('installments_44', 12, 2)->nullable();
            $table->decimal('installments_55', 12, 2)->nullable();
            $table->timestamps();
            
            $table->unique('lot_id');
            $table->index(['precio_contado']);
            $table->index(['precio_venta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lot_financial_templates');
    }
};
