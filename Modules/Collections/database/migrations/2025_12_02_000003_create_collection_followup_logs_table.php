<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_followup_logs', function (Blueprint $table) {
            $table->bigIncrements('log_id');
            $table->unsignedBigInteger('followup_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('channel'); // whatsapp, sms, email, call, letter
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_followup_logs');
    }
};

