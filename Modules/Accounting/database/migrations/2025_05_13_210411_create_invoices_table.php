<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id('invoice_id');

            // CAMPO SIN SER FK, SE AGREGARA EL FK EN UNA MIGRACION EXTERNA
            $t->unsignedBigInteger('contract_id');


            $t->date('issue_date');
            $t->decimal('amount', 14, 2);
            $t->char('currency', 3);
            $t->string('document_number', 30)->unique();
            $t->enum('sunat_status', [
                'pendiente',
                'enviado',
                'aceptado',
                'observado',
                'rechazado'
            ])->default('pendiente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
