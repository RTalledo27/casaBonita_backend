<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id('bonus_id');
            $table->foreignId('employee_id')->constrained('employees', 'employee_id')->onDelete('cascade');
            $table->string('bonus_type');
            $table->string('bonus_name');
            $table->decimal('bonus_amount', 10, 2);
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->decimal('achieved_amount', 12, 2)->nullable();
            $table->decimal('achievement_percentage', 5, 2)->nullable();
            $table->enum('payment_status', ['pendiente', 'pagado', 'cancelado'])->default('pendiente');
            $table->date('payment_date')->nullable();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_quarter')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('employees', 'employee_id');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
