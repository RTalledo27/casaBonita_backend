<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'advisor_id')) {
                $table->foreignId('advisor_id')->nullable()->after('reservation_id')->constrained('employees', 'employee_id')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'advisor_id')) {
                $table->dropForeign(['advisor_id']);
                $table->dropColumn('advisor_id');
            }
        });
    }
};
