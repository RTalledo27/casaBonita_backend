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
        // Verificar si la columna ya existe antes de crearla
        if (!Schema::hasColumn('contracts', 'contract_number')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('contract_number', 50)->unique()->after('contract_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('contract_number');
        });
    }
};