<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id('log_id');
            $t->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            $t->enum('action', ['insert', 'update', 'delete']);
            $t->string('entity', 60);
            $t->unsignedBigInteger('entity_id');
            $t->timestamp('timestamp')->useCurrent();
            $t->json('changes');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
