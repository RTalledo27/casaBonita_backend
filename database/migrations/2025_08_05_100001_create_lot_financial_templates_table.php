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
            $table->unsignedBigInteger('lot_id');
            $table->decimal('precio_lista', 12, 2)->nullable();
            $table->decimal('descuento', 5, 2)->nullable();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->decimal('precio_contado', 12, 2)->nullable(); // Precio específico para pago al contado
            $table->decimal('cuota_balon', 12, 2)->nullable();
            $table->decimal('bono_bpp', 12, 2)->nullable();
            $table->decimal('cuota_inicial', 12, 2)->nullable();
            $table->decimal('ci_fraccionamiento', 12, 2)->nullable();
            $table->decimal('installments_24', 8, 2)->nullable(); // Cuota para 24 meses
            $table->decimal('installments_40', 8, 2)->nullable(); // Cuota para 40 meses
            $table->decimal('installments_44', 8, 2)->nullable(); // Cuota para 44 meses
            $table->decimal('installments_55', 8, 2)->nullable(); // Cuota para 55 meses
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('lot_id')->references('lot_id')->on('lots')->onDelete('cascade');
            
            // Unique constraint to ensure one template per lot
            $table->unique('lot_id');
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