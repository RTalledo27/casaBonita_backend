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
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id('journal_entry_id');
            $t->date('date');
            $t->string('description', 255)->nullable();
            $t->foreignId('posted_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            $t->enum('status', ['draft', 'posted'])->default('draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
