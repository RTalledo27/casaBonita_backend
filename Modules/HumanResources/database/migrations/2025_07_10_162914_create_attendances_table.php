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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->onDelete('cascade');
            $table->date('attendance_date');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->timestamp('break_start_time')->nullable();
            $table->timestamp('break_end_time')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->decimal('regular_hours', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->enum('status', ['presente', 'ausente', 'tardanza', 'falta_justificada', 'licencia', 'feriado'])->default('presente');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees', 'employee_id');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
