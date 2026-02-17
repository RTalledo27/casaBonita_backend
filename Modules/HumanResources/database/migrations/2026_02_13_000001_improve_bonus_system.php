<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add office_id to teams (team belongs to an office)
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('office_id')
                ->nullable()
                ->after('team_leader_id')
                ->constrained('offices', 'office_id')
                ->onDelete('set null');
        });

        // 2. Add monthly_goal to offices
        Schema::table('offices', function (Blueprint $table) {
            $table->decimal('monthly_goal', 12, 2)
                ->nullable()
                ->default(0)
                ->after('city');
        });

        // 3. Add target_value to bonus_goals (missing column referenced by service)
        Schema::table('bonus_goals', function (Blueprint $table) {
            $table->decimal('target_value', 12, 2)
                ->nullable()
                ->after('bonus_percentage')
                ->comment('Target count or amount for goal calculation');
        });

        // 4. Add office_id to bonus_goals (for office-level goals)
        Schema::table('bonus_goals', function (Blueprint $table) {
            $table->foreignId('office_id')
                ->nullable()
                ->after('team_id')
                ->constrained('offices', 'office_id')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bonus_goals', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropColumn('office_id');
        });

        Schema::table('bonus_goals', function (Blueprint $table) {
            $table->dropColumn('target_value');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('monthly_goal');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropColumn('office_id');
        });
    }
};
