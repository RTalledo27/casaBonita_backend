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
        if (!Schema::hasTable('payment_schedules')) {
            return;
        }
        
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->decimal('amount_paid', 14, 2)->nullable()->after('amount');
            $table->date('payment_date')->nullable()->after('amount_paid');
            $table->string('payment_method', 50)->nullable()->after('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('payment_schedules')) {
            return;
        }
        
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'payment_date', 'payment_method']);
        });
    }
};