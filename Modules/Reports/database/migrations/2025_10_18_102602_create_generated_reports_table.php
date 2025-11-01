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
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('scheduled_report_id')->nullable();
            $table->string('type'); // 'sales', 'payment_schedules', 'projections', etc.
            $table->json('filters_used'); // Filtros aplicados al generar el reporte
            $table->string('format'); // 'pdf', 'excel', 'csv'
            $table->string('file_path')->nullable(); // Ruta del archivo generado
            $table->string('status'); // 'generating', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->integer('file_size')->nullable(); // Tamaño en bytes
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Para limpieza automática
            $table->unsignedBigInteger('generated_by');
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('report_templates');
            $table->foreign('scheduled_report_id')->references('id')->on('scheduled_reports');
            $table->foreign('generated_by')->references('user_id')->on('users');
            $table->index(['status', 'generated_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
