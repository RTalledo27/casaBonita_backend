<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) model_has_permissions.permission_id → permissions.id
        Schema::table('model_has_permissions', function (Blueprint $t) {
            $t->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();
        });

        // 2) model_has_roles.role_id → roles.role_id
        Schema::table('model_has_roles', function (Blueprint $t) {
            $t->foreign('role_id')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnDelete();
        });

        // 3) role_has_permissions.permission_id → permissions.id
        //    role_has_permissions.role_id       → roles.role_id
        Schema::table('role_has_permissions', function (Blueprint $t) {
            $t->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $t->foreign('role_id')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('model_has_permissions', fn(Blueprint $t) => $t->dropForeign(['permission_id']));
        Schema::table('model_has_roles',       fn(Blueprint $t) => $t->dropForeign(['role_id']));
        Schema::table('role_has_permissions', function (Blueprint $t) {
            $t->dropForeign(['permission_id']);
            $t->dropForeign(['role_id']);
        });
    }
};
