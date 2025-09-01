<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add financial fields to contracts table (only if they don't exist)
        $contractsColumns = Schema::getColumnListing('contracts');
        
        Schema::table('contracts', function (Blueprint $table) use ($contractsColumns) {
            if (!in_array('funding', $contractsColumns)) {
                $table->decimal('funding', 15, 2)->default(0)->after('financing_amount')->comment('Monto de financiamiento (duplicado de financing_amount para compatibilidad)');
            }
            if (!in_array('bpp', $contractsColumns)) {
                $table->decimal('bpp', 15, 2)->default(0)->after('funding')->comment('Bono del Buen Pagador');
            }
            if (!in_array('bfh', $contractsColumns)) {
                $table->decimal('bfh', 15, 2)->default(0)->after('bpp')->comment('Bono Familiar Habitacional');
            }
            if (!in_array('initial_quota', $contractsColumns)) {
                $table->decimal('initial_quota', 15, 2)->default(0)->after('bfh')->comment('Cuota inicial del contrato');
            }
        });

        // 2. Migrate existing data from lots to contracts
        // Check if financial fields exist in lots table before migrating
        $lotsColumns = Schema::getColumnListing('lots');
        
        if (in_array('BPP', $lotsColumns) && in_array('BFH', $lotsColumns) && in_array('initial_quota', $lotsColumns)) {
            // First, migrate BPP, BFH, and initial_quota if they exist
            DB::statement('
                UPDATE contracts c 
                INNER JOIN reservations r ON c.reservation_id = r.reservation_id
                INNER JOIN lots l ON r.lot_id = l.lot_id
                SET 
                    c.bpp = COALESCE(l.BPP, 0),
                    c.bfh = COALESCE(l.BFH, 0),
                    c.initial_quota = COALESCE(l.initial_quota, 0)
            ');
        }
        
        // Migrate funding only if financing_amount is empty or zero and funding column exists
        if (in_array('funding', $lotsColumns)) {
            DB::statement('
                UPDATE contracts c 
                INNER JOIN reservations r ON c.reservation_id = r.reservation_id
                INNER JOIN lots l ON r.lot_id = l.lot_id
                SET c.funding = COALESCE(l.funding, 0)
                WHERE (c.financing_amount = 0 OR c.financing_amount IS NULL) AND l.funding > 0
            ');
        }
        
        // For contracts where financing_amount already exists, copy it to funding for consistency
        DB::statement('
            UPDATE contracts 
            SET funding = financing_amount 
            WHERE financing_amount > 0 AND funding = 0
        ');

        // 3. Log migration results
        $migratedCount = DB::selectOne('
            SELECT COUNT(*) as count 
            FROM contracts 
            WHERE funding > 0 OR bpp > 0 OR bfh > 0 OR initial_quota > 0
        ')->count;

        echo "Migrated financial data for {$migratedCount} contracts\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore data to lots before removing columns
        DB::statement('
            UPDATE lots l
            INNER JOIN reservations r ON l.lot_id = r.lot_id
            INNER JOIN contracts c ON r.reservation_id = c.reservation_id
            SET 
                l.funding = c.funding,
                l.BPP = c.bpp,
                l.BFH = c.bfh,
                l.initial_quota = c.initial_quota
        ');

        // Remove financial fields from contracts
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['funding', 'bpp', 'bfh', 'initial_quota']);
        });
    }
};