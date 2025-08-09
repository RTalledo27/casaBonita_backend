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
        Schema::create('commission_payment_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commission_id');
            $table->unsignedBigInteger('customer_payment_id');
            $table->enum('payment_installment', ['first', 'second']);
            $table->enum('verification_status', [
                'pending',
                'verified',
                'failed',
                'reversed'
            ])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->decimal('payment_amount', 10, 2);
            $table->date('payment_date');
            $table->text('verification_notes')->nullable();
            $table->json('verification_metadata')->nullable(); // Para almacenar datos adicionales
            $table->timestamps();
            
            // Índices y claves foráneas
            $table->foreign('commission_id')->references('commission_id')->on('commissions')->onDelete('cascade');
            $table->foreign('customer_payment_id')->references('payment_id')->on('customer_payments')->onDelete('cascade');
            $table->foreign('verified_by')->references('user_id')->on('users')->onDelete('set null');
            
            // Índices para optimizar consultas
            $table->index(['commission_id', 'payment_installment'], 'cpv_commission_installment_idx');
            $table->index(['verification_status'], 'cpv_status_idx');
            $table->index(['verified_at'], 'cpv_verified_at_idx');
            
            // Restricción única para evitar duplicados
            $table->unique(['commission_id', 'payment_installment'], 'unique_commission_installment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payment_verifications');
    }
};