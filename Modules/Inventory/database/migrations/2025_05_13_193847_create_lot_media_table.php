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
        Schema::create('lot_media', function (Blueprint $t) {
            $t->id('media_id');
            $t->foreignId('lot_id')->constrained('lots', 'lot_id')->cascadeOnDelete();
            $t->string('url', 255);
            $t->enum('type', ['foto', 'plano', 'video', 'doc']);
            $t->timestamp('uploaded_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lot_media');
    }
};
