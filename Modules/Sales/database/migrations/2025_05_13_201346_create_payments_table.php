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
        Schema::create('payments', function (Blueprint $t) {
            $t->id('payment_id');
            // FK a payment_schedules.schedule_id
            $t->foreignId('schedule_id')
                ->constrained('payment_schedules', 'schedule_id')
                ->cascadeOnDelete();
            //CAMPO SIN SER FK, SE AGREGARA EN UNA MIGRACION EXTERNA
            $t->unsignedBigInteger('journal_entry_id')->nullable();


            $t->date('payment_date');
            $t->decimal('amount', 14, 2);
            $t->enum('method', ['transferencia', 'efectivo', 'tarjeta','yape', 'plin', 'otro'])
                ->default('efectivo');
            $t->string('reference', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
