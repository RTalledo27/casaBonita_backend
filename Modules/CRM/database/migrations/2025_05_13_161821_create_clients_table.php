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
        Schema::create('clients', function (Blueprint $t) {
            $t->id('client_id');
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->enum('doc_type', ['DNI', 'CE', 'RUC', 'PAS']);
            $t->string('doc_number', 20)->unique();
            $t->string('email', 120)->nullable();
            $t->integer('primary_phone')->nullable();
            $t->integer('secondary_phone')->nullable();
            $t->enum('marital_status', ['soltero', 'casado', 'divorciado', 'viudo'])->default('soltero');
            $t->enum('type', ['lead', 'client', 'provider']);
            $t->dateTime('date')->nullable();
            $t->string('occupation')->nullable();
            $t->decimal('salary', 14, 2)->nullable();
            $t->string('family_group')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
