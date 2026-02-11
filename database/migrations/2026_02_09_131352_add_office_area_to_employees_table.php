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
        // First, migrate existing 'office' string values to the new offices table
        $existingOffices = DB::table('employees')
            ->whereNotNull('office')
            ->where('office', '!=', '')
            ->select('office')
            ->distinct()
            ->pluck('office');

        foreach ($existingOffices as $officeName) {
            $normalized = mb_strtolower(trim($officeName));
            DB::table('offices')->insertOrIgnore([
                'name' => trim($officeName),
                'name_normalized' => $normalized,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('employees', function (Blueprint $table) {
            // Add new foreign key columns
            $table->unsignedBigInteger('office_id')->nullable()->after('team_id');
            $table->unsignedBigInteger('area_id')->nullable()->after('office_id');

            // Add foreign key constraints
            $table->foreign('office_id')->references('office_id')->on('offices')->onDelete('set null');
            $table->foreign('area_id')->references('area_id')->on('areas')->onDelete('set null');
        });

        // Update employees with their office_id based on the old 'office' string
        $offices = DB::table('offices')->get();
        foreach ($offices as $office) {
            DB::table('employees')
                ->where('office', $office->name)
                ->update(['office_id' => $office->office_id]);
        }

        // Now remove the old 'office' string column
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('office');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('office')->nullable()->after('team_id');
        });

        // Restore office names from office_id
        $offices = DB::table('offices')->get();
        foreach ($offices as $office) {
            DB::table('employees')
                ->where('office_id', $office->office_id)
                ->update(['office' => $office->name]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['area_id']);
            $table->dropColumn(['office_id', 'area_id']);
        });
    }
};
