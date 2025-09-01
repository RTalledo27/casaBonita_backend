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
        Schema::table('clients', function (Blueprint $table) {
            // Change phone columns from integer to string to handle long phone numbers
            $table->string('primary_phone', 20)->nullable()->change();
            $table->string('secondary_phone', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Revert back to integer type
            $table->integer('primary_phone')->nullable()->change();
            $table->integer('secondary_phone')->nullable()->change();
        });
    }
};