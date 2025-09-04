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
        Schema::table('accounts_receivable', function (Blueprint $table) {
            // Modificar el enum de currency para incluir DOP
            $table->enum('currency', ['PEN', 'USD', 'DOP'])->default('PEN')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            // Revertir al enum original
            $table->enum('currency', ['PEN', 'USD'])->default('PEN')->change();
        });
    }
};
