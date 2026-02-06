<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla para ítems de comprobantes electrónicos
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('invoice_id');
            
            // Descripción del producto/servicio
            $table->string('description', 500);
            $table->string('product_code', 30)->nullable()->comment('Código producto SUNAT');
            
            // Cantidades y unidades
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit_code', 3)->default('NIU')->comment('NIU=Unidad, ZZ=Servicio');
            
            // Precios
            $table->decimal('unit_price', 14, 2)->comment('Precio unitario sin IGV');
            $table->decimal('unit_price_with_igv', 14, 2)->comment('Precio unitario con IGV');
            
            // Impuestos
            $table->decimal('igv_amount', 14, 2)->default(0);
            $table->decimal('igv_percentage', 5, 2)->default(18.00);
            $table->string('igv_type', 2)->default('10')->comment('10=Gravado, 20=Exonerado, 30=Inafecto');
            
            // Totales
            $table->decimal('subtotal', 14, 2)->comment('quantity * unit_price');
            $table->decimal('total', 14, 2)->comment('subtotal + igv');
            
            // Orden
            $table->unsignedSmallInteger('order')->default(1);
            
            $table->timestamps();
            
            // FK
            $table->foreign('invoice_id')
                  ->references('invoice_id')
                  ->on('invoices')
                  ->onDelete('cascade');
                  
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
