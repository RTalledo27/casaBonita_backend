<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_schedules', 'amount_paid')) {
                $table->decimal('amount_paid', 14, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('payment_schedules', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('amount_paid');
            }
            if (!Schema::hasColumn('payment_schedules', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('payment_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_schedules')) {
            return;
        }

        Schema::table('payment_schedules', function (Blueprint $table) {
            $cols = [];
            foreach (['amount_paid', 'payment_date', 'payment_method'] as $col) {
                if (Schema::hasColumn('payment_schedules', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

