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
        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id('line_id');
            $t->foreignId('journal_entry_id')
                ->constrained('journal_entries', 'journal_entry_id')
                ->cascadeOnDelete();
            $t->foreignId('account_id')
                ->constrained('chart_of_accounts', 'account_id');
            $t->foreignId('lot_id')
                ->nullable()
                ->constrained('lots', 'lot_id')
                ->nullOnDelete();
            $t->decimal('debit', 14, 2)->default(0);
            $t->decimal('credit', 14, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
