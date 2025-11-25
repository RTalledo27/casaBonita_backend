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
            // Agregar campo sale_type: 'cash' (contado) o 'financed' (financiada)
            $table->enum('sale_type', ['cash', 'financed'])->default('financed')->after('status');
            $table->index('sale_type'); // Índice para consultas de comisiones
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('sale_type');
        });
    }
};
