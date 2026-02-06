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
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id('attachment_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('service_requests')
                ->onDelete('cascade');

            $table->foreign('uploaded_by')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
