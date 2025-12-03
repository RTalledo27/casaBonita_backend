<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->string('segment')->nullable()->after('general_reason');
            $table->string('tramo')->nullable()->after('segment');
            $table->string('channel')->nullable()->after('tramo');
            $table->date('commitment_date')->nullable()->after('channel');
            $table->decimal('commitment_amount', 12, 2)->nullable()->after('commitment_date');
            $table->decimal('lot_area_m2', 10, 2)->nullable()->after('lot');
            $table->string('lot_status')->nullable()->after('lot_area_m2');
        });
    }

    public function down(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->dropColumn(['segment','tramo','channel','commitment_date','commitment_amount','lot_area_m2','lot_status']);
        });
    }
};

