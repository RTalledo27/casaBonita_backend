<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $t) {
            $t->id('log_id');
            $t->string('service', 60);
            $t->string('entity', 60);
            $t->unsignedBigInteger('entity_id');
            $t->enum('status', ['success', 'error']);
            $t->string('message', 255)->nullable();
            $t->timestamp('logged_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
