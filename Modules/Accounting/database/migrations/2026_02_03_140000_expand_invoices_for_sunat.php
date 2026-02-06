<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expandir tabla invoices para facturación electrónica SUNAT
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Tipo de documento SUNAT
            $table->enum('document_type', ['01', '03', '07', '08'])
                  ->default('03')
                  ->after('invoice_id')
                  ->comment('01=Factura, 03=Boleta, 07=NC, 08=ND');
            
            // Serie y correlativo
            $table->string('series', 4)->after('document_type')->comment('F001, B001, etc.');
            $table->unsignedBigInteger('correlative')->after('series');
            
            // Datos del cliente
            $table->enum('client_document_type', ['1', '6', '0'])->default('1')
                  ->after('correlative')
                  ->comment('1=DNI, 6=RUC, 0=Otros');
            $table->string('client_document_number', 15)->after('client_document_type');
            $table->string('client_name', 200)->after('client_document_number');
            $table->string('client_address', 300)->nullable()->after('client_name');
            
            // Montos
            $table->decimal('subtotal', 14, 2)->default(0)->after('amount');
            $table->decimal('igv', 14, 2)->default(0)->after('subtotal');
            $table->decimal('total', 14, 2)->default(0)->after('igv');
            
            // Datos SUNAT
            $table->longText('xml_content')->nullable()->comment('XML firmado');
            $table->string('xml_hash', 100)->nullable()->comment('Hash del XML');
            $table->longText('cdr_content')->nullable()->comment('CDR de SUNAT');
            $table->string('cdr_code', 10)->nullable()->comment('Código respuesta SUNAT');
            $table->text('cdr_description')->nullable()->comment('Descripción respuesta');
            
            // PDF y QR
            $table->string('pdf_path', 255)->nullable();
            $table->text('qr_code')->nullable()->comment('Contenido QR para impresión');
            
            // Fechas y estado
            $table->timestamp('sent_at')->nullable()->comment('Fecha envío a SUNAT');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            // Para notas de crédito/débito
            $table->unsignedBigInteger('related_invoice_id')->nullable()
                  ->comment('Documento al que afecta (para NC/ND)');
            $table->string('void_reason', 100)->nullable();
            
            // Índices
            $table->unique(['series', 'correlative'], 'invoices_series_correlative_unique');
            $table->index('document_type');
            $table->index('sunat_status');
            $table->index('client_document_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_series_correlative_unique');
            $table->dropIndex(['document_type']);
            $table->dropIndex(['sunat_status']);
            $table->dropIndex(['client_document_number']);
            
            $table->dropColumn([
                'document_type', 'series', 'correlative',
                'client_document_type', 'client_document_number', 'client_name', 'client_address',
                'subtotal', 'igv', 'total',
                'xml_content', 'xml_hash', 'cdr_content', 'cdr_code', 'cdr_description',
                'pdf_path', 'qr_code', 'sent_at', 'created_at', 'updated_at',
                'related_invoice_id', 'void_reason'
            ]);
        });
    }
};
