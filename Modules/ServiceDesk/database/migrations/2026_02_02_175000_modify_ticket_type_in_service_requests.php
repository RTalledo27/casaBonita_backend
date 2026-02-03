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
        Schema::table('service_requests', function (Blueprint $table) {
            // Change ticket_type from enum to string to support all ticket types
            $table->string('ticket_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Revert to enum (warning: might fail if data contains other values)
            $table->enum('ticket_type', ['garantia', 'mantenimiento', 'otro'])->change();
        });
    }
};
