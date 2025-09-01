<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->foreign('journal_entry_id')
                ->references('journal_entry_id')
                ->on('journal_entries')
                ->nullOnDelete();          // ON DELETE SET NULL
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->foreign('contract_id')
                ->references('contract_id')
                ->on('contracts')
                ->cascadeOnDelete();        // ON DELETE CASCADE
        });

     
        
    }

    public function down(): void
    {
        Schema::table('payments',  fn(Blueprint $t) => $t->dropForeign(['journal_entry_id']));
        Schema::table('invoices',  fn(Blueprint $t) => $t->dropForeign(['contract_id']));
    }
};
