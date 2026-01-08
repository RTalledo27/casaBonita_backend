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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 100)->unique()->index();
            $table->string('event_type', 100)->index();
            $table->string('correlation_id', 100)->nullable()->index();
            $table->string('source_id', 100)->nullable()->index();
            $table->text('payload');
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'failed_permanently'])->default('pending')->index();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('headers')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
