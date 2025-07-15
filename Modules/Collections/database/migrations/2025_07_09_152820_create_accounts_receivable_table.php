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
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id('ar_id'); // ar = accounts receivable
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('ar_number', 20)->unique();
            $table->string('invoice_number', 50)->nullable();
            $table->string('description', 500);
            $table->decimal('original_amount', 15, 2);
            $table->decimal('outstanding_amount', 15, 2);
            $table->enum('currency', ['PEN', 'USD'])->default('PEN');
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['PENDING', 'PARTIAL', 'PAID', 'OVERDUE', 'CANCELLED'])->default('PENDING');
            $table->unsignedBigInteger('assigned_collector_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['client_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('assigned_collector_id');
            $table->index('ar_number');

            // Claves foráneas
            $table->foreign('client_id')->references('client_id')->on('clients');
            $table->foreign('contract_id')->references('contract_id')->on('contracts');
            $table->foreign('assigned_collector_id')->references('user_id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts_receivable');
    }};
