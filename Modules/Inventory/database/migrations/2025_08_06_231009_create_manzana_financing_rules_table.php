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
        Schema::create('manzana_financing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manzana_id')->constrained('manzanas', 'manzana_id')->onDelete('cascade');
            $table->enum('financing_type', ['cash_only', 'installments', 'mixed'])->default('mixed');
            $table->integer('max_installments')->nullable();
            $table->decimal('min_down_payment_percentage', 5, 2)->nullable();
            $table->boolean('allows_balloon_payment')->default(false);
            $table->boolean('allows_bpp_bonus')->default(false);
            $table->timestamps();
            
            $table->unique('manzana_id');
            $table->index(['financing_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manzana_financing_rules');
    }
};
