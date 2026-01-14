<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logicware_payments', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->nullable()->index();
            $table->string('correlation_id', 128)->nullable()->index();
            $table->string('source_id', 64)->nullable()->index();

            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->unsignedInteger('installment_number')->nullable()->index();

            $table->string('external_payment_number', 80)->nullable()->index();
            $table->dateTime('payment_date')->nullable()->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();

            $table->string('method', 60)->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('user_name', 120)->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'external_payment_number'], 'lw_payments_msg_ext_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logicware_payments');
    }
};

