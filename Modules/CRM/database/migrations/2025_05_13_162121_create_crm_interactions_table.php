<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_interactions', function (Blueprint $t) {
            $t->id('interaction_id');

            // PK client_id en clients
            $t->unsignedBigInteger('client_id');
            $t->foreign('client_id')
                ->references('client_id')
                ->on('clients')
                ->cascadeOnDelete();

            // PK user_id en users
            $t->unsignedBigInteger('user_id');
           

            $t->dateTime('date');
            $t->enum('channel', ['call', 'email', 'whatsapp', 'visit', 'other']);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_interactions');
    }
};
