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
        Schema::create('users', function (Blueprint $t) {
            $t->id('user_id');
            $t->string('username', 60)->unique();
            $t->char('password_hash', 60);
            $t->string('email', 120)->unique();
            $t->enum('status', ['active', 'blocked'])->default('active');
            $t->string('photo_profile', 255)->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
