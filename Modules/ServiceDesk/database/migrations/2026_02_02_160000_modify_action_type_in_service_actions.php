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
        Schema::table('service_actions', function (Blueprint $table) {
            // Change action_type from enum to string to support more types
            $table->string('action_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_actions', function (Blueprint $table) {
            // Revert to enum (warning: might fail if data contains other values)
            $table->enum('action_type', ['comentario', 'cambio_estado', 'escalado'])->change();
        });
    }
};
