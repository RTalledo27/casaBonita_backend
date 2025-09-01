<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->string('pdf_path')->nullable()->after('status');
        });
        DB::statement("ALTER TABLE contracts MODIFY status ENUM('pendiente_aprobacion','vigente','resuelto','cancelado') DEFAULT 'pendiente_aprobacion'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY status ENUM('vigente','resuelto','cancelado') DEFAULT 'vigente'");
        Schema::table('contracts', function (Blueprint $t) {
            $t->dropColumn('pdf_path');
        });
    }
};
