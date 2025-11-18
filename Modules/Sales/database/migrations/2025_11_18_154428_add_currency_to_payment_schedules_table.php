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
            // Moneda de la cuota (PEN, USD, etc.)
            $table->string('currency', 3)->default('PEN')->after('amount');
            
            // Fecha de pago real (cuando la cuota fue pagada)
            $table->date('paid_date')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn(['currency', 'paid_date']);
        });
    }
};
