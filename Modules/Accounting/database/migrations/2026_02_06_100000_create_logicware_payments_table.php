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
        if (!Schema::hasTable('logicware_payments')) {
            Schema::create('logicware_payments', function (Blueprint $table) {
                $table->id('logicware_payment_id');
                $table->unsignedBigInteger('schedule_id')->nullable()->index();
                $table->string('method')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('reference_number')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->date('payment_date')->nullable();
                $table->timestamps();

                // Foreign key constraint (optional, depending on strictness requirements)
                // $table->foreign('schedule_id')->references('schedule_id')->on('payment_schedules')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logicware_payments');
    }
};
