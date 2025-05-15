<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams      = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        throw_if(empty($tableNames), new Exception('Error: config/permission.php not loaded.'));

        //
        // 1) Tabla de permisos
        //
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        //
        // 2) Tabla de roles (con role_id y description)
        //
        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('role_id');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name', 60)->unique();
            $table->string('guard_name');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            if ($teams) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        //
        // 3) Pivote model_has_permissions (SIN FK)
        //
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index(
                [$columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_model_id_model_type_index'
            );
            $table->primary(
                [$columnNames['permission_pivot_key'], $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        //
        // 4) Pivote model_has_roles (SIN FK)
        //
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            $table->unsignedBigInteger($columnNames['role_pivot_key']);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index(
                [$columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_model_id_model_type_index'
            );
            $table->primary(
                [$columnNames['role_pivot_key'], $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        //
        // 5) Pivote role_has_permissions (SIN FK)
        //
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($columnNames) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);
            $table->unsignedBigInteger($columnNames['role_pivot_key']);
            $table->primary(
                [$columnNames['permission_pivot_key'], $columnNames['role_pivot_key']],
                'role_has_permissions_permission_id_role_id_primary'
            );
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
