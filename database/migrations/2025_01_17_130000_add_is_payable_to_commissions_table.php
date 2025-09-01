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
        // Verificar si la tabla commissions existe antes de modificarla
        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                // Verificar si la columna no existe ya
                if (!Schema::hasColumn('commissions', 'is_payable')) {
                    // Campo para indicar si la comisión es pagable
                    $table->boolean('is_payable')->default(true);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                if (Schema::hasColumn('commissions', 'is_payable')) {
                    $table->dropColumn('is_payable');
                }
            });
        }
    }
};