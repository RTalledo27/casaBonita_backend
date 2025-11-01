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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Usuario destinatario
            $table->string('title'); // Título de la notificación
            $table->text('message')->nullable(); // Mensaje detallado
            $table->enum('type', ['info', 'success', 'warning', 'error'])->default('info'); // Tipo
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium'); // Prioridad
            $table->boolean('is_read')->default(false); // Leída o no
            $table->string('related_module')->nullable(); // 'contracts', 'payments', etc.
            $table->unsignedBigInteger('related_id')->nullable(); // ID del registro relacionado
            $table->string('related_url')->nullable(); // URL directa
            $table->string('icon')->nullable(); // Nombre del ícono Lucide (opcional)
            $table->timestamp('read_at')->nullable(); // Cuándo se leyó
            $table->timestamp('expires_at')->nullable(); // Auto-eliminar viejas
            $table->timestamps(); // created_at, updated_at
            
            // Índices para optimizar consultas
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
            
            // Clave foránea (sin cascade por ahora para evitar problemas)
            // Si quieres cascade, asegúrate que la tabla users exista primero
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
