<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_cut_items')) {
            return;
        }

        Schema::table('sales_cut_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_cut_items', 'payment_id')) {
                $table->unsignedBigInteger('payment_id')->nullable()->after('payment_schedule_id');
                $table->index('payment_id');
                $table->unique(['cut_id', 'payment_id']);
                $table->foreign('payment_id')->references('payment_id')->on('payments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_cut_items')) {
            return;
        }

        if (!Schema::hasColumn('sales_cut_items', 'payment_id')) {
            return;
        }

        Schema::table('sales_cut_items', function (Blueprint $table) {
            $table->dropUnique(['cut_id', 'payment_id']);
            $table->dropForeign(['payment_id']);
            $table->dropIndex(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};

