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
        Schema::create('reservations', function (Blueprint $t) {
            $t->id('reservation_id');
            $t->foreignId('lot_id')->constrained('lots', 'lot_id')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients', 'client_id')->cascadeOnDelete();
            $t->date('reservation_date');
            $t->date('expiration_date');
            $t->decimal('deposit_amount', 14, 2)->default(100);
            $t->enum('status', ['activa', 'expirada', 'cancelada', 'convertida'])
                ->default('activa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
