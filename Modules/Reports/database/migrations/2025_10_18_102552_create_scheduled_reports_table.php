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
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('template_id');
            $table->string('frequency'); // 'daily', 'weekly', 'monthly', 'quarterly'
            $table->json('schedule_config'); // Configuración específica del horario
            $table->json('recipients'); // Lista de emails para envío
            $table->json('filters')->nullable(); // Filtros específicos para este reporte programado
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('report_templates');
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->index(['is_active', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
