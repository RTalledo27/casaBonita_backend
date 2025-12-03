<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('client_id');
            $table->string('dni')->nullable()->after('client_name');
            $table->string('phone1')->nullable()->after('dni');
            $table->string('phone2')->nullable()->after('phone1');
            $table->string('email')->nullable()->after('phone2');
            $table->string('address')->nullable()->after('email');
            $table->string('district')->nullable()->after('address');
            $table->string('province')->nullable()->after('district');
            $table->string('department')->nullable()->after('province');
            $table->string('lot')->nullable()->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('collection_followups', function (Blueprint $table) {
            $table->dropColumn(['client_name','dni','phone1','phone2','email','address','district','province','department','lot']);
        });
    }
};

