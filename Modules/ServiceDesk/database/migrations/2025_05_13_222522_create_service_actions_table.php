<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_actions', function (Blueprint $t) {
            $t->id('action_id');
            $t->foreignId('ticket_id')
                ->constrained('service_requests', 'ticket_id')
                ->cascadeOnDelete();
            $t->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            $t->dateTime('performed_at');
            $t->text('notes')->nullable();
            $t->date('next_action_date')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('service_actions');
    }
};
