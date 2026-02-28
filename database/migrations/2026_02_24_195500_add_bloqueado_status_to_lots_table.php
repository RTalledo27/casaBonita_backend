<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega 'bloqueado' y 'no_disponible' al ENUM de status en la tabla lots.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE lots MODIFY COLUMN status ENUM('disponible', 'reservado', 'vendido', 'bloqueado', 'no_disponible') NOT NULL DEFAULT 'disponible'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert values back before shrinking ENUM
        DB::statement("UPDATE lots SET status = 'disponible' WHERE status IN ('bloqueado', 'no_disponible')");
        DB::statement("ALTER TABLE lots MODIFY COLUMN status ENUM('disponible', 'reservado', 'vendido') NOT NULL DEFAULT 'disponible'");
    }
};
