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
        Schema::create('spouses', function (Blueprint $t) {
            $t->id('spouse_id');
            $t->foreignId('client_id')->constrained('clients', 'client_id')->cascadeOnDelete();
            $t->foreignId('partner_id')->constrained('clients','client_id')->cascadeOnDelete();
            $t->unique(['client_id', 'partner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spouses');
    }
};
