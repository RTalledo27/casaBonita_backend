<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla para control de series y correlativos de comprobantes
     */
    public function up(): void
    {
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->id('series_id');
            
            // Tipo y serie
            $table->enum('document_type', ['01', '03', '07', '08'])
                  ->comment('01=Factura, 03=Boleta, 07=NC, 08=ND');
            $table->string('series', 4)->comment('F001, B001, FC01, BC01');
            
            // Control de correlativos
            $table->unsignedBigInteger('current_correlative')->default(0);
            
            // Estado y ambiente
            $table->boolean('is_active')->default(true);
            $table->enum('environment', ['beta', 'production'])->default('beta');
            
            // Descripción
            $table->string('description', 100)->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->unique(['document_type', 'series', 'environment'], 'series_unique');
        });

        // Insertar series por defecto para ambiente beta
        DB::table('invoice_series')->insert([
            ['document_type' => '03', 'series' => 'B001', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Boletas Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => '01', 'series' => 'F001', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Facturas Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => '07', 'series' => 'BC01', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Notas Crédito Boleta Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => '07', 'series' => 'FC01', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Notas Crédito Factura Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => '08', 'series' => 'BD01', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Notas Débito Boleta Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => '08', 'series' => 'FD01', 'current_correlative' => 0, 'environment' => 'beta', 'description' => 'Notas Débito Factura Beta', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_series');
    }
};
