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
        Schema::table('payment_schedules', function (Blueprint $table) {
            // Agregar columna 'type' para clasificar el tipo de cuota
            $table->enum('type', ['inicial', 'financiamiento', 'balon', 'bono_bpp'])
                ->default('financiamiento')
                ->after('status')
                ->comment('Tipo de cuota: inicial, financiamiento, balón, o bono BPP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
