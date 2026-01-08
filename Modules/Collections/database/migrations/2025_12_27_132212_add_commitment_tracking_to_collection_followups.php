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
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->enum('commitment_status', ['pending', 'fulfilled', 'broken'])->nullable()->after('commitment_amount');
            $table->text('commitment_notes')->nullable()->after('commitment_status');
            $table->date('commitment_fulfilled_date')->nullable()->after('commitment_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->dropColumn(['commitment_status', 'commitment_notes', 'commitment_fulfilled_date']);
        });
    }
};
