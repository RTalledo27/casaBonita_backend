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
            $table->decimal('base_price', 12, 2)->nullable()->after('total_price')->comment('Precio base o de lista del lote');
            $table->decimal('unit_price', 12, 2)->nullable()->after('base_price')->comment('Precio unitario de venta (antes de descuentos)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'unit_price']);
        });
    }
};
