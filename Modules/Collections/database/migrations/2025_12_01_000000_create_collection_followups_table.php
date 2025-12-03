<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_followups', function (Blueprint $table) {
            $table->bigIncrements('followup_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('assigned_employee_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('sale_code')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->decimal('monthly_quota', 12, 2)->nullable();
            $table->integer('paid_installments')->nullable();
            $table->integer('pending_installments')->nullable();
            $table->integer('total_installments')->nullable();
            $table->integer('overdue_installments')->nullable();
            $table->decimal('pending_amount', 12, 2)->nullable();
            $table->date('contact_date')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('management_result')->nullable();
            $table->text('management_notes')->nullable();
            $table->date('home_visit_date')->nullable();
            $table->string('home_visit_reason')->nullable();
            $table->string('home_visit_result')->nullable();
            $table->text('home_visit_notes')->nullable();
            $table->string('management_status')->nullable();
            $table->string('owner')->nullable();
            $table->text('general_notes')->nullable();
            $table->string('general_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_followups');
    }
};

