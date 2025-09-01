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
        Schema::table('users', function (Blueprint $table) {
            // Datos personales
            $table->string('first_name')->nullable()->after('username');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('dni')->nullable()->unique()->after('last_name');
            $table->string('phone')->nullable()->after('dni');
            $table->date('birth_date')->nullable()->after('phone');

            // Datos laborales
            $table->string('position')->nullable()->after('birth_date');
            $table->string('department')->nullable()->after('position');
            $table->date('hire_date')->nullable()->after('department');

            // Foto ya existe como 'photo_profile' y status también.

            // Usuario que lo creó
            $table->unsignedBigInteger('created_by')
                ->nullable()
                ->after('photo_profile');
            $table->foreign('created_by')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
