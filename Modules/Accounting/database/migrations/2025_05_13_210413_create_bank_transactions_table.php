<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $t) {
            $t->id('txn_id');
            $t->foreignId('bank_account_id')
                ->constrained('bank_accounts', 'bank_account_id');
            $t->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries', 'journal_entry_id')
                ->nullOnDelete();
            $t->date('date');
            $t->decimal('amount', 14, 2);
            $t->char('currency', 3);
            $t->string('reference', 80)->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
