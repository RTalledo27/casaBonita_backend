<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id('employee_id');
                $table->foreignId('user_id')->unique()->constrained('users', 'user_id')->onDelete('cascade');
                $table->string('employee_code')->unique();
                $table->enum('employee_type', ['asesor_inmobiliario', 'vendedor', 'administrativo', 'gerente', 'jefe_ventas']);
                $table->decimal('base_salary', 10, 2)->default(0);
                $table->decimal('variable_salary', 10, 2)->nullable();
                $table->decimal('commission_percentage', 5, 2)->nullable()->comment('Porcentaje de comisión base');
                $table->decimal('individual_goal', 12, 2)->nullable()->comment('Meta de venta individual mensual en monto');
                $table->boolean('is_commission_eligible')->default(false);
                $table->boolean('is_bonus_eligible')->default(false);
                $table->string('bank_account')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_cci')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->string('emergency_contact_relationship')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('supervisor_id')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('termination_date')->nullable();
                $table->enum('employment_status', ['activo', 'inactivo', 'de_vacaciones', 'licencia', 'terminado'])->default('activo');
                $table->string('contract_type')->nullable();
                $table->string('work_schedule')->nullable();
                $table->string('social_security_number')->nullable();
                $table->string('afp_code')->nullable();
                $table->string('cuspp')->nullable();
                $table->string('health_insurance')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};