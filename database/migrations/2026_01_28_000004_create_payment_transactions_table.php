<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('start_schedule_id')->nullable();

            $table->date('payment_date')->nullable();
            $table->decimal('amount_total', 14, 2)->default(0);
            $table->string('method', 50)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('contract_id');
            $table->index('start_schedule_id');
            $table->index('payment_date');
            $table->index('reference');

            $table->foreign('contract_id')->references('contract_id')->on('contracts')->nullOnDelete();
            $table->foreign('start_schedule_id')->references('schedule_id')->on('payment_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::dropIfExists('payment_transactions');
    }
};

