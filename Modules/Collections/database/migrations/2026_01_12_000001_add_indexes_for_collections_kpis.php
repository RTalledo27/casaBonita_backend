<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->index('assigned_employee_id', 'idx_cf_assigned_employee');
            $table->index('contract_id', 'idx_cf_contract');
            $table->index('due_date', 'idx_cf_due_date');
            $table->index('commitment_date', 'idx_cf_commitment_date');
            $table->index('commitment_status', 'idx_cf_commitment_status');
            $table->index(['assigned_employee_id', 'due_date'], 'idx_cf_assigned_due_date');
            $table->index(['assigned_employee_id', 'commitment_date'], 'idx_cf_assigned_commitment_date');
        });

        Schema::table('collection_followup_logs', function (Blueprint $table) {
            $table->index('employee_id', 'idx_cfl_employee');
            $table->index('logged_at', 'idx_cfl_logged_at');
            $table->index(['employee_id', 'logged_at'], 'idx_cfl_employee_logged_at');
            $table->index(['channel', 'logged_at'], 'idx_cfl_channel_logged_at');
        });

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->index(['status', 'paid_date'], 'idx_ps_status_paid_date');
            $table->index('contract_id', 'idx_ps_contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->dropIndex('idx_cf_assigned_employee');
            $table->dropIndex('idx_cf_contract');
            $table->dropIndex('idx_cf_due_date');
            $table->dropIndex('idx_cf_commitment_date');
            $table->dropIndex('idx_cf_commitment_status');
            $table->dropIndex('idx_cf_assigned_due_date');
            $table->dropIndex('idx_cf_assigned_commitment_date');
        });

        Schema::table('collection_followup_logs', function (Blueprint $table) {
            $table->dropIndex('idx_cfl_employee');
            $table->dropIndex('idx_cfl_logged_at');
            $table->dropIndex('idx_cfl_employee_logged_at');
            $table->dropIndex('idx_cfl_channel_logged_at');
        });

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_ps_status_paid_date');
            $table->dropIndex('idx_ps_contract_id');
        });
    }
};

