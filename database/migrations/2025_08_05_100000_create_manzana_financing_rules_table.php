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
            $table->unsignedBigInteger('manzana_id');
            $table->enum('financing_type', ['cash_only', 'installments', 'mixed']);
            $table->integer('max_installments')->nullable();
            $table->decimal('min_down_payment_percentage', 5, 2)->nullable();
            $table->boolean('allows_balloon_payment')->default(false);
            $table->boolean('allows_bpp_bonus')->default(false);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('manzana_id')->references('manzana_id')->on('manzanas')->onDelete('cascade');
            
            // Unique constraint to ensure one rule per manzana
            $table->unique('manzana_id', 'unique_manzana_financing');
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