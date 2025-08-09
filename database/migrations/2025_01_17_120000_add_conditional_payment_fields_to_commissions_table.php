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
        Schema::table('commissions', function (Blueprint $table) {
            // Campos para comisiones condicionadas a pagos del cliente
            $table->boolean('requires_client_payment_verification')->default(false)->after('sales_count');
            $table->enum('payment_verification_status', [
                'pending_verification',
                'first_payment_verified', 
                'second_payment_verified',
                'fully_verified',
                'verification_failed'
            ])->default('pending_verification')->after('requires_client_payment_verification');
            $table->timestamp('first_payment_verified_at')->nullable()->after('payment_verification_status');
            $table->timestamp('second_payment_verified_at')->nullable()->after('first_payment_verified_at');
            $table->boolean('is_eligible_for_payment')->default(true)->after('second_payment_verified_at');
            $table->text('verification_notes')->nullable()->after('is_eligible_for_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn([
                'requires_client_payment_verification',
                'payment_verification_status',
                'first_payment_verified_at',
                'second_payment_verified_at',
                'is_eligible_for_payment',
                'verification_notes'
            ]);
        });
    }
};