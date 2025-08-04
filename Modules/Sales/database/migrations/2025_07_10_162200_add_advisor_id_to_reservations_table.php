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
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'advisor_id')) {
                $table->foreignId('advisor_id')->nullable()->after('client_id')->constrained('employees', 'employee_id')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'advisor_id')) {
                $table->dropForeign(['advisor_id']);
                $table->dropColumn('advisor_id');
            }
        });
    }
};