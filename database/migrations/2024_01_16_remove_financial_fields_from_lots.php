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
        Schema::table('lots', function (Blueprint $table) {
            // Remove financial fields that have been migrated to contracts table
            $table->dropColumn([
                'funding',
                'BPP', 
                'BFH',
                'initial_quota'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            // Restore financial fields if rollback is needed
            $table->decimal('funding', 15, 2)->default(0)->after('total_price');
            $table->decimal('BPP', 15, 2)->default(0)->after('funding');
            $table->decimal('BFH', 15, 2)->default(0)->after('BPP');
            $table->decimal('initial_quota', 15, 2)->default(0)->after('BFH');
        });
    }
};