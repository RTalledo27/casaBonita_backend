<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->string('contract_status')->nullable()->after('sale_code');
            $table->unsignedBigInteger('advisor_id')->nullable()->after('contract_status');
            $table->string('advisor_name')->nullable()->after('advisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->dropColumn(['contract_status','advisor_id','advisor_name']);
        });
    }
};

