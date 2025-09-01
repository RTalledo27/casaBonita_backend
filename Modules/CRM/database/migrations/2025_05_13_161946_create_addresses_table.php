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
        Schema::create('addresses', function (Blueprint $t) {
            $t->id('address_id');
            $t->foreignId('client_id')
                ->constrained('clients', 'client_id')
                ->cascadeOnDelete();
            $t->string('line1', 120);
            $t->string('line2', 120)->nullable();
            $t->string('city', 60);
            $t->string('state', 60)->nullable();
            $t->string('country', 60);
            $t->string('zip_code', 15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
