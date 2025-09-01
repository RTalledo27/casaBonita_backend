<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id('commission_id');
                $table->foreignId('employee_id')->constrained('employees', 'employee_id')->onDelete('cascade');
                $table->foreignId('contract_id')->constrained('contracts', 'contract_id')->onDelete('cascade');
                $table->string('commission_type');
                $table->decimal('sale_amount', 12, 2);
                $table->integer('installment_plan')->nullable();
                $table->decimal('commission_percentage', 5, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->enum('payment_status', ['pendiente', 'pagado', 'cancelado'])->default('pendiente');
                $table->date('payment_date')->nullable();
                $table->unsignedTinyInteger('period_month');
                $table->unsignedSmallInteger('period_year');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};