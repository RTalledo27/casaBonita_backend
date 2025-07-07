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
        Schema::table('service_requests', function (Blueprint $table) {
            Schema::table('service_requests', function (Blueprint $table) {
                // Si no existe, agregar opened_by
                if (!Schema::hasColumn('service_requests', 'opened_by')) {
                    $table->foreignId('opened_by')
                        ->after('contract_id')
                        ->constrained('users', 'user_id')
                        ->cascadeOnDelete();
                }
    
                // Hacer contract_id nullable si no lo es
                $table->unsignedBigInteger('contract_id')->nullable()->change();
    
                // SLA y escalado si no existen
                if (!Schema::hasColumn('service_requests', 'sla_due_at')) {
                    $table->dateTime('sla_due_at')->nullable()->after('description');
                }
                if (!Schema::hasColumn('service_requests', 'escalated_at')) {
                    $table->dateTime('escalated_at')->nullable()->after('sla_due_at');
                }
    
                // Timestamps y softDeletes si no existen
                if (!Schema::hasColumn('service_requests', 'created_at')) {
                    $table->timestamps();
                }
                if (!Schema::hasColumn('service_requests', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('service_requests', 'opened_by')) {
                $table->dropForeign(['opened_by']);
                    $table->dropColumn('opened_by');
                }
                // Si quieres revertir nullable contract_id
                // $table->unsignedBigInteger('contract_id')->nullable(false)->change();
                if (Schema::hasColumn('service_requests', 'sla_due_at')) {
                    $table->dropColumn('sla_due_at');
                }
                if (Schema::hasColumn('service_requests', 'escalated_at')) {
                    $table->dropColumn('escalated_at');
                }
                if (Schema::hasColumn('service_requests', 'created_at')) {
                    $table->dropTimestamps();
                }
                if (Schema::hasColumn('service_requests', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
    }

    
};
