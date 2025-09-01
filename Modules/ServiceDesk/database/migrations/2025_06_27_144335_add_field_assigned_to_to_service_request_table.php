<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Si no existe, agregar el campo y el foreign
            if (!Schema::hasColumn('service_requests', 'assigned_to')) {
                $table->foreignId('assigned_to')
                    ->nullable()
                    ->after('opened_by')
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('service_requests', 'assigned_to')) {
                $table->dropForeign(['assigned_to']);
                $table->dropColumn('assigned_to');
            }
        });
    }
};
