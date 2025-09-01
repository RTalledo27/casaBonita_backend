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
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('ar_id');
            $table->string('payment_number', 20)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('currency', ['PEN', 'USD'])->default('PEN');
            $table->enum('payment_method', ['CASH', 'TRANSFER', 'CHECK', 'CARD', 'YAPE', 'PLIN', 'OTHER']);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['client_id', 'payment_date']);
            $table->index(['ar_id', 'payment_date']);
            $table->index('payment_date');
            $table->index('payment_method');
            $table->index('processed_by');
            $table->index('payment_number');

            // Claves foráneas
            $table->foreign('client_id')->references('client_id')->on('clients');
            $table->foreign('ar_id')->references('ar_id')->on('accounts_receivable')->onDelete('cascade');
            $table->foreign('processed_by')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_payments');
    }
};
