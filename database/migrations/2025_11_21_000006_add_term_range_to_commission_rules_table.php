<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTermRangeToCommissionRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->integer('term_min_months')->nullable()->after('term_group');
            $table->integer('term_max_months')->nullable()->after('term_min_months');
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
            $table->dropColumn(['term_min_months', 'term_max_months']);
        });
    }
}
