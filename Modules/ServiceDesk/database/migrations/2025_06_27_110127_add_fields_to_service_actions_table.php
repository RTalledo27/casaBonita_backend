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
        Schema::table('service_actions', function (Blueprint $table) {
            // Agregar action_type si no existe
            if (!Schema::hasColumn('service_actions', 'action_type')) {
                $table->enum('action_type', ['comentario', 'cambio_estado', 'escalado'])
                    ->default('comentario')
                    ->after('user_id');
            }
            // Timestamps y softDeletes si no existen
            if (!Schema::hasColumn('service_actions', 'created_at')) {
                $table->timestamps();
            }
            if (!Schema::hasColumn('service_actions', 'deleted_at')) {
                $table->softDeletes();
            }
            
            if(!Schema::hasColumn('service_actions', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_actions', function (Blueprint $table) {
            if (Schema::hasColumn('service_actions', 'action_type')) {
                $table->dropColumn('action_type');
            }
            if (Schema::hasColumn('service_actions', 'created_at')) {
                $table->dropTimestamps();
            }
            if (Schema::hasColumn('service_actions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if(Schema::hasColumn('service_actions', 'assigned_to')) {
                $table->dropForeign(['assigned_to']);
                $table->dropColumn('assigned_to');
            }
        });
    }
};
