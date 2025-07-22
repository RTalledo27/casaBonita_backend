<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        //relacionar bono con tipo de bonificacion
        Schema::table('bonuses', function (Blueprint $table) {
            $table->foreignId('bonus_type_id')->nullable()->after('employee_id')->constrained('bonus_types', 'bonus_type_id')->onDelete('set null');
            $table->foreignId('bonus_goal_id')->nullable()->after('bonus_type_id')->constrained('bonus_goals', 'bonus_goal_id')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->after('period_quarter')->constrained('employees', 'employee_id')->onDelete('set null');

            // Modificar columna existente
            $table->string('bonus_type', 50)->nullable()->change();

            // Agregar índices
            $table->index(['bonus_type_id', 'payment_status']);
            $table->index(['bonus_goal_id', 'payment_status']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        //
        Schema::table('bonuses', function (Blueprint $table) {
            $table->dropForeign(['bonus_type_id']);
            $table->dropForeign(['bonus_goal_id']);
            $table->dropForeign(['created_by']);

            $table->dropColumn(['bonus_type_id', 'bonus_goal_id', 'created_by']);

            $table->string('bonus_type', 50)->nullable(false)->change();
        });

    }
};
