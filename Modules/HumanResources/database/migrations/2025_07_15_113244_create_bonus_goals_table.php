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
        Schema::create('bonus_goals', function (Blueprint $table) {
            $table->id('bonus_goal_id');
            $table->foreignId('bonus_type_id')->constrained('bonus_types', 'bonus_type_id')->onDelete('cascade');
            $table->string('goal_name', 100);
            $table->decimal('min_achievement', 10, 2);
            $table->decimal('max_achievement', 10, 2)->nullable();
            $table->decimal('bonus_amount', 10, 2)->nullable();
            $table->decimal('bonus_percentage', 5, 2)->nullable();
            $table->string('employee_type', 50)->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams', 'team_id')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bonus_type_id', 'is_active']);
            $table->index(['employee_type', 'is_active']);
            $table->index(['team_id', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_goals');
    }
};
