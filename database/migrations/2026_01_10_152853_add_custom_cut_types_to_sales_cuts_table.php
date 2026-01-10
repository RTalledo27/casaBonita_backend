<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para incluir 'custom', 'quarterly', 'yearly'
        DB::statement("ALTER TABLE sales_cuts MODIFY COLUMN cut_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom') DEFAULT 'daily'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE sales_cuts MODIFY COLUMN cut_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily'");
    }
};
