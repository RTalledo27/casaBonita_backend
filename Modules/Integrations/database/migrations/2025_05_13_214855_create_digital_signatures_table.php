<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_signatures', function (Blueprint $t) {
            $t->id('signature_id');
            $t->string('entity', 60);
            $t->unsignedBigInteger('entity_id');
            $t->char('hash', 64);
            $t->text('certificate');
            $t->timestamp('signed_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('digital_signatures');
    }
};
