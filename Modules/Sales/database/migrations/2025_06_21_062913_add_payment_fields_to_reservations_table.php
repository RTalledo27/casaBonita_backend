<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->enum('status', ['pendiente_pago', 'completada', 'cancelada', 'convertida'])
                ->default('pendiente_pago')->change();
            $t->string('deposit_method', 20)->nullable()->after('deposit_amount');
            $t->string('deposit_reference', 60)->nullable()->after('deposit_method');
            $t->timestamp('deposit_paid_at')->nullable()->after('deposit_reference');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn(['deposit_method', 'deposit_reference', 'deposit_paid_at']);
        });
        DB::statement("ALTER TABLE reservations MODIFY status ENUM('activa','expirada','cancelada','convertida') DEFAULT 'activa'");
    }
};
