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
        Schema::table('lot_media', function (Blueprint $t) {
            $t->unsignedInteger('position')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lot_media', function (Blueprint $t) {
            $t->dropColumn('position');
        });
    }
};
