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
        Schema::table('payrolls', function (Blueprint $table) {
            // Agregar descuento de EsSalud del empleado (9%)
            $table->decimal('employee_essalud', 10, 2)->default(0)->after('employer_essalud')->comment('Descuento EsSalud del empleado (9%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('employee_essalud');
        });
    }
};
