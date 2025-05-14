<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $t) {
            $t->id('ticket_id');
            $t->foreignId('contract_id')
                ->constrained('contracts', 'contract_id')
                ->cascadeOnDelete();
            $t->dateTime('opened_at');
            $t->enum('ticket_type', ['garantia', 'mantenimiento', 'otro']);
            $t->enum('priority', ['baja', 'media', 'alta', 'critica'])->default('media');
            $t->enum('status', ['abierto', 'en_proceso', 'cerrado'])->default('abierto');
            $t->text('description')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
