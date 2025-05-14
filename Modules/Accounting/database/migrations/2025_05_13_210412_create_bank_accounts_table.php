<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id('bank_account_id');
            $t->string('bank_name', 80);
            $t->char('currency', 3);
            $t->string('account_number', 34)->unique();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
