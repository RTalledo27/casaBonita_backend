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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id('payroll_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->onDelete('cascade');
            $table->string('payroll_period', 7); // YYYY-MM
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->date('pay_date');
            $table->decimal('base_salary', 10, 2);
            $table->decimal('commissions_amount', 10, 2)->default(0);
            $table->decimal('bonuses_amount', 10, 2)->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->decimal('other_income', 10, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('income_tax', 10, 2)->default(0);
            $table->decimal('social_security', 10, 2)->default(0); // ONP/AFP
            $table->decimal('health_insurance', 10, 2)->default(0); // EPS
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_salary', 12, 2);
            $table->string('currency', 3)->default('PEN');
            $table->enum('status', ['borrador', 'procesado', 'aprobado', 'pagado'])->default('borrador');
            $table->foreignId('processed_by')->nullable()->constrained('employees', 'employee_id');
            $table->foreignId('approved_by')->nullable()->constrained('employees', 'employee_id');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
