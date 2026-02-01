<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'contract_id')) {
                $table->unsignedBigInteger('contract_id')->nullable()->after('schedule_id');
                $table->index('contract_id');
            }
        });

        if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payments', 'contract_id')) {
            DB::statement('
                UPDATE payments p
                INNER JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id
                SET p.contract_id = ps.contract_id
                WHERE p.contract_id IS NULL
            ');
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'contract_id')) {
                $table->foreign('contract_id')
                    ->references('contract_id')
                    ->on('contracts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (!Schema::hasColumn('payments', 'contract_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropIndex(['contract_id']);
            $table->dropColumn('contract_id');
        });
    }
};

