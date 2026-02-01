<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'transaction_id')) {
                $table->unsignedBigInteger('transaction_id')->nullable()->after('payment_id');
                $table->index('transaction_id');
                $table->foreign('transaction_id')->references('transaction_id')->on('payment_transactions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (!Schema::hasColumn('payments', 'transaction_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};

