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
            // Check if columns exist before dropping them
            $columns = Schema::getColumnListing('lots');
            
            $columnsToRemove = [];
            
            if (in_array('funding', $columns)) {
                $columnsToRemove[] = 'funding';
            }
            if (in_array('BPP', $columns)) {
                $columnsToRemove[] = 'BPP';
            }
            if (in_array('BFH', $columns)) {
                $columnsToRemove[] = 'BFH';
            }
            if (in_array('initial_quota', $columns)) {
                $columnsToRemove[] = 'initial_quota';
            }
            
            // Only drop columns if they exist
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
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