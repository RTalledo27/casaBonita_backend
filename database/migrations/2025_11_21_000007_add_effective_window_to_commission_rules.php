<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEffectiveWindowToCommissionRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('term_max_months');
            $table->date('effective_to')->nullable()->after('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
}
