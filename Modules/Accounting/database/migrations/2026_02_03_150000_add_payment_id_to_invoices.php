<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('contract_id');
            // No agregamos foreing key estricta por ahora para evitar problemas con módulos separados, 
            // pero idealmente debería tenerla. Lo manejaremos a nivel lógico.
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
    }
};
