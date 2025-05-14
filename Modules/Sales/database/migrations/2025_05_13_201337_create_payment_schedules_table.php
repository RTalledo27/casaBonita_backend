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
        Schema::create('payment_schedules', function (Blueprint $t) {
            $t->id('schedule_id');
            $t->foreignId('contract_id')->constrained('contracts', 'contract_id')
                ->cascadeOnDelete();
            $t->date('due_date');
            $t->decimal('amount', 14, 2);
            $t->enum('status', ['pendiente', 'pagado', 'vencido'])
                ->default('pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
