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
        Schema::create('contract_import_logs', function (Blueprint $table) {
            $table->id('import_log_id');
            $table->unsignedBigInteger('user_id');
            $table->string('file_name');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->text('message')->nullable();
            $table->integer('total_rows')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('error_details')->nullable();
            $table->integer('processing_time')->nullable()->comment('Tiempo en segundos');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            
            // Nota: La clave foránea se agregará después cuando la tabla users esté disponible
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_import_logs');
    }
};