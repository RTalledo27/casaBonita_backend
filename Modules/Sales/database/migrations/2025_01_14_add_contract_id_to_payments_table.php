<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si la tabla payments existe antes de modificarla
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                // Verificar si la columna contract_id no existe ya
                if (!Schema::hasColumn('payments', 'contract_id')) {
                    // Primero agregar la columna sin foreign key
                    $table->unsignedBigInteger('contract_id')->after('schedule_id')->nullable();
                }
            });
            
            // Poblar contract_id basándose en la relación con payment_schedules
            if (Schema::hasTable('payment_schedules')) {
                DB::statement('
                    UPDATE payments p 
                    INNER JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id 
                    SET p.contract_id = ps.contract_id
                ');
            }
            
            Schema::table('payments', function (Blueprint $table) {
                // Verificar si la columna existe y no tiene foreign key ya
                if (Schema::hasColumn('payments', 'contract_id')) {
                    // Hacer la columna NOT NULL y agregar foreign key
                    $table->unsignedBigInteger('contract_id')->nullable(false)->change();
                    $table->foreign('contract_id')
                        ->references('contract_id')
                        ->on('contracts')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'contract_id')) {
                    $table->dropForeign(['contract_id']);
                    $table->dropColumn('contract_id');
                }
            });
        }
    }
};