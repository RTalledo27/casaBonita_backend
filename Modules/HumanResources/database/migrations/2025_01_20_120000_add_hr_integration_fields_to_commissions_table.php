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
        if (!Schema::hasTable('commissions')) {
            return;
        }
        
        Schema::table('commissions', function (Blueprint $table) {
            // Campos para integración HR-Collections
            $table->string('verification_status')->default('pending')->after('is_eligible_for_payment');
            $table->unsignedBigInteger('customer_id')->nullable()->after('verification_status');
            $table->date('period_start')->nullable()->after('customer_id');
            $table->date('period_end')->nullable()->after('period_start');
            $table->timestamp('verified_at')->nullable()->after('period_end');
            $table->decimal('verified_amount', 10, 2)->nullable()->after('verified_at');
            $table->string('eligible_date')->nullable()->after('verified_amount');
            
            // Índices para mejorar rendimiento
            $table->index('verification_status');
            $table->index('customer_id');
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('commissions')) {
            return;
        }
        
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['period_start', 'period_end']);
            
            $table->dropColumn([
                'verification_status',
                'customer_id',
                'period_start',
                'period_end',
                'verified_at',
                'verified_amount',
                'eligible_date'
            ]);
        });
    }
};