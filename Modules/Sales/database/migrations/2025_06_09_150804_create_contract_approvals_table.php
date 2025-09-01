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
        Schema::create('contract_approvals', function (Blueprint $t) {
            $t->id('approval_id');
            $t->foreignId('contract_id')->constrained('contracts', 'contract_id')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $t->enum('status', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $t->timestamp('approved_at')->nullable();
            $t->text('comments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_approvals');
    }
};
