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
        Schema::create('family_members', function (Blueprint $t) {
            $t->id('family_member_id');
            $t->foreignId('client_id')->constrained('clients', 'client_id')->cascadeOnDelete();
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('dni', 20);
            $t->string('relation', 60);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
