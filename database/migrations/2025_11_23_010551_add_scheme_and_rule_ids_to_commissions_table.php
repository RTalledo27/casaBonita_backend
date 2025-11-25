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
        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_scheme_id')->nullable()->after('is_payable');
            $table->unsignedBigInteger('commission_rule_id')->nullable()->after('commission_scheme_id');
            $table->timestamp('applied_at')->nullable()->after('commission_rule_id');
            
            // Foreign keys
            $table->foreign('commission_scheme_id')->references('id')->on('commission_schemes')->onDelete('set null');
            $table->foreign('commission_rule_id')->references('id')->on('commission_rules')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['commission_scheme_id']);
            $table->dropForeign(['commission_rule_id']);
            $table->dropColumn(['commission_scheme_id', 'commission_rule_id', 'applied_at']);
        });
    }
};
