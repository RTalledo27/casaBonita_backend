<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SLA Configurations table
        Schema::create('sla_configs', function (Blueprint $table) {
            $table->id();
            $table->string('priority'); // baja, media, alta, critica
            $table->integer('response_hours'); // Time to first response
            $table->integer('resolution_hours'); // Time to resolution
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('priority');
        });

        // Service Categories table
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable(); // lucide icon name
            $table->string('color')->default('blue');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add closed_by field to service_requests
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('closed_by')->nullable()->after('assigned_to');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->unsignedBigInteger('category_id')->nullable()->after('ticket_type');
            
            $table->foreign('closed_by')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('service_categories')->onDelete('set null');
        });

        // Insert default SLA configurations
        DB::table('sla_configs')->insert([
            ['priority' => 'critica', 'response_hours' => 1, 'resolution_hours' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['priority' => 'alta', 'response_hours' => 4, 'resolution_hours' => 24, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['priority' => 'media', 'response_hours' => 24, 'resolution_hours' => 72, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['priority' => 'baja', 'response_hours' => 48, 'resolution_hours' => 168, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert default categories
        DB::table('service_categories')->insert([
            ['name' => 'Incidente', 'description' => 'Problemas técnicos o fallas', 'icon' => 'alert-triangle', 'color' => 'red', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Solicitud', 'description' => 'Peticiones de servicio', 'icon' => 'message-square', 'color' => 'blue', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cambio', 'description' => 'Solicitudes de cambio', 'icon' => 'refresh-cw', 'color' => 'yellow', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Garantía', 'description' => 'Reclamos de garantía', 'icon' => 'shield', 'color' => 'purple', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mantenimiento', 'description' => 'Solicitudes de mantenimiento', 'icon' => 'wrench', 'color' => 'green', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['closed_by', 'closed_at', 'category_id']);
        });
        
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('sla_configs');
    }
};
