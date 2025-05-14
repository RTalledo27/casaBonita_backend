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
        Schema::create('lots', function (Blueprint $t) {
            $t->id('lot_id');

            $t->foreignId('manzana_id')->constrained('manzanas', 'manzana_id')->cascadeOnDelete();

            $t->foreignId('street_type_id')->constrained('street_types', 'street_type_id')->cascadeOnDelete();
            
            $t->tinyInteger('num_lot');
            $t->decimal('area_m2', 10, 2);
            $t->decimal('area_construction_m2', 14, 2)->nullable();
            $t->decimal('total_price', 14, 2);
            $t->decimal('funding', 14, 2)->nullable();
            $t->decimal('BPP', 14, 2)->nullable();
            $t->decimal('BFH', 14, 2)->nullable();
            $t->decimal('initial_quota', 14, 2)->nullable();
            $t->char('currency', 3);
            $t->enum('status', ['disponible', 'reservado', 'vendido'])
                ->default('disponible');
            $t->unique(['manzana_id', 'num_lot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
