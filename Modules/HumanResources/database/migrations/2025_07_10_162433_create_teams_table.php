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
        Schema::create('teams', function (Blueprint $table) {
            $table->id('team_id');
            $table->string('team_name');
            $table->string('team_code')->unique()->nullable();
            $table->text('description')->nullable();
            $table->foreignId('team_leader_id')->nullable()->constrained('employees', 'employee_id')->onDelete('set null');
            $table->decimal('monthly_goal', 12, 2)->nullable()->comment('Meta de venta mensual del equipo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Añadir la clave foránea a la tabla de empleados después de crear la tabla de equipos
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('team_id')->references('team_id')->on('teams')->onDelete('set null');
            $table->foreign('supervisor_id')->references('employee_id')->on('employees')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['supervisor_id']);
        });
        Schema::dropIfExists('teams');
    }
};
