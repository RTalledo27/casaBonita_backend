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
        Schema::create('contracts', function (Blueprint $t) {
            $t->id('contract_id');
            $t->foreignId('reservation_id')->constrained('reservations','reservation_id');
            $t->string('contract_number', 50)->unique();
            $t->date('sign_date');
            $t->decimal('total_price', 14, 2);
            $t->char('currency', 3);
            $t->enum('status', ['vigente', 'resuelto', 'cancelado'])
                ->default('vigente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
