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
        Schema::create('incentives', function (Blueprint $table) {
            $table->id('incentive_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->onDelete('cascade');
            $table->string('incentive_name');
            $table->text('description');
            $table->decimal('amount', 10, 2);
            $table->text('target_description')->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', ['activo', 'completado', 'pagado', 'cancelado', 'expirado'])->default('activo');
            $table->foreignId('created_by')->constrained('employees', 'employee_id');
            $table->foreignId('approved_by')->nullable()->constrained('employees', 'employee_id');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentives');
    }
};
