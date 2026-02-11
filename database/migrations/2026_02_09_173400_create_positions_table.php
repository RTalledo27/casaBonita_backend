<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla positions
        Schema::create('positions', function (Blueprint $table) {
            $table->id('position_id');
            $table->string('name', 100);                    // "Asesor de Ventas"
            $table->string('name_normalized', 100)->unique(); // "asesor de ventas"
            $table->enum('category', ['ventas', 'admin', 'tech', 'gerencia', 'operaciones'])->default('admin');
            $table->boolean('is_commission_eligible')->default(false);
            $table->boolean('is_bonus_eligible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Agregar position_id a employees (nullable para no romper nada)
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('position_id')->nullable()->after('employee_type');
            $table->foreign('position_id')->references('position_id')->on('positions')->onDelete('set null');
        });

        // 3. Seed de posiciones iniciales basado en employee_types existentes
        $positions = [
            ['name' => 'Asesor de Ventas', 'category' => 'ventas', 'is_commission_eligible' => true, 'is_bonus_eligible' => true],
            ['name' => 'Asesor Inmobiliario', 'category' => 'ventas', 'is_commission_eligible' => true, 'is_bonus_eligible' => true],
            ['name' => 'Gerente de Ventas', 'category' => 'ventas', 'is_commission_eligible' => true, 'is_bonus_eligible' => true],
            ['name' => 'Jefe de Ventas', 'category' => 'ventas', 'is_commission_eligible' => true, 'is_bonus_eligible' => true],
            ['name' => 'Arquitecto', 'category' => 'operaciones', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Arquitecta', 'category' => 'operaciones', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Community Manager', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Ingeniero de Sistemas', 'category' => 'tech', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Ing. Sistemas y Analista de Datos', 'category' => 'tech', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Director de Tecnología', 'category' => 'gerencia', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Director de Desarrollo Comercial e Institucional', 'category' => 'gerencia', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Gerente General', 'category' => 'gerencia', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Jefe de Administración y Finanzas', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Finanzas', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Gestor de Cobranzas', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Servicio al Cliente', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'BackOffice', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Audiovisual', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
            ['name' => 'Tracker', 'category' => 'admin', 'is_commission_eligible' => false, 'is_bonus_eligible' => false],
        ];

        $now = now();
        foreach ($positions as $pos) {
            DB::table('positions')->insert([
                'name' => $pos['name'],
                'name_normalized' => mb_strtolower(trim($pos['name'])),
                'category' => $pos['category'],
                'is_commission_eligible' => $pos['is_commission_eligible'],
                'is_bonus_eligible' => $pos['is_bonus_eligible'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. Migrar datos existentes: mapear employee_type -> position_id
        $typeToPosition = [
            'asesor_inmobiliario' => 'asesor inmobiliario',
            'jefa_de_ventas' => 'jefe de ventas',
            'arquitecto' => 'arquitecto',
            'arquitecta' => 'arquitecta',
            'community_manager_corporativo' => 'community manager',
            'ingeniero_de_sistemas' => 'ingeniero de sistemas',
            'diseñador_audiovisual_area_de_marketing' => 'audiovisual',
            'encargado_de_ti' => 'ingeniero de sistemas',
            'director' => 'director de tecnología',
            'analista_de_administracion' => 'finanzas',
            'tracker' => 'tracker',
            'contadora_junior' => 'finanzas',
        ];

        foreach ($typeToPosition as $type => $posName) {
            $positionId = DB::table('positions')->where('name_normalized', $posName)->value('position_id');
            if ($positionId) {
                DB::table('employees')->where('employee_type', $type)->update(['position_id' => $positionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });

        Schema::dropIfExists('positions');
    }
};
