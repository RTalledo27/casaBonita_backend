<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedBigInteger('locked_by')->nullable()->after('status');
            $table->string('lock_reason', 255)->nullable()->after('locked_by');
            $table->timestamp('locked_at')->nullable()->after('lock_reason');

            $table->foreign('locked_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index('locked_by');
        });

        // Agregar 'en_proceso' al enum de status
        DB::statement("ALTER TABLE lots MODIFY COLUMN status ENUM('disponible', 'reservado', 'vendido', 'bloqueado', 'no_disponible', 'en_proceso') NOT NULL DEFAULT 'disponible'");
    }

    public function down(): void
    {
        // Revertir lotes en_proceso a disponible
        DB::statement("UPDATE lots SET status = 'disponible' WHERE status = 'en_proceso'");
        DB::statement("ALTER TABLE lots MODIFY COLUMN status ENUM('disponible', 'reservado', 'vendido', 'bloqueado', 'no_disponible') NOT NULL DEFAULT 'disponible'");

        Schema::table('lots', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_by', 'lock_reason', 'locked_at']);
        });
    }
};
