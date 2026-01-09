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
        Schema::create('sales_cuts', function (Blueprint $table) {
            $table->id('cut_id');
            $table->date('cut_date')->comment('Fecha del corte');
            $table->enum('cut_type', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->enum('status', ['open', 'closed', 'reviewed', 'exported'])->default('open');
            
            // Métricas de ventas
            $table->integer('total_sales_count')->default(0)->comment('Total de ventas del período');
            $table->decimal('total_revenue', 15, 2)->default(0)->comment('Ingresos totales por ventas');
            $table->decimal('total_down_payments', 15, 2)->default(0)->comment('Total de cuotas iniciales');
            
            // Métricas de pagos recibidos
            $table->integer('total_payments_count')->default(0)->comment('Total de pagos recibidos');
            $table->decimal('total_payments_received', 15, 2)->default(0)->comment('Total cobrado en el día');
            $table->integer('paid_installments_count')->default(0)->comment('Cuotas pagadas');
            
            // Comisiones
            $table->decimal('total_commissions', 15, 2)->default(0)->comment('Comisiones generadas');
            
            // Balance
            $table->decimal('cash_balance', 15, 2)->default(0)->comment('Balance de efectivo');
            $table->decimal('bank_balance', 15, 2)->default(0)->comment('Balance bancario');
            
            // Metadata
            $table->text('notes')->nullable();
            $table->json('summary_data')->nullable()->comment('Datos adicionales del resumen');
            
            // Auditoría
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('cut_date');
            $table->index('status');
            $table->index(['cut_date', 'cut_type']);
            
            // Foreign keys
            $table->foreign('closed_by')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('user_id')->on('users')->onDelete('set null');
        });

        Schema::create('sales_cut_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('cut_id');
            
            // Tipo de item
            $table->enum('item_type', ['sale', 'payment', 'commission'])->comment('Tipo: venta, pago o comisión');
            
            // Referencias
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            
            // Montos
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0)->nullable();
            
            // Método de pago (para pagos)
            $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'debit_card', 'check'])->nullable();
            
            // Metadata
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('cut_id');
            $table->index('item_type');
            $table->index('contract_id');
            $table->index('payment_schedule_id');
            
            // Foreign keys
            $table->foreign('cut_id')->references('cut_id')->on('sales_cuts')->onDelete('cascade');
            $table->foreign('contract_id')->references('contract_id')->on('contracts')->onDelete('cascade');
            $table->foreign('payment_schedule_id')->references('schedule_id')->on('payment_schedules')->onDelete('cascade');
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_cut_items');
        Schema::dropIfExists('sales_cuts');
    }
};
