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
        Schema::create('async_import_processes', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'lot_import', 'contract_import', etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('total_rows')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('async_import_processes');
    }
};