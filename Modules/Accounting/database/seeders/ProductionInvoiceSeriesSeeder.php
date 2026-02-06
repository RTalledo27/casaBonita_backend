<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\InvoiceSeries;
use Modules\Accounting\Models\Invoice;

class ProductionInvoiceSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Boleta Electrónica (B001)
        InvoiceSeries::updateOrCreate(
            [
                'document_type' => Invoice::TYPE_BOLETA,
                'series' => 'B001',
                'environment' => 'production'
            ],
            [
                'current_correlative' => 0,
                'is_active' => true,
                'description' => 'Serie Principal Boletas (Producción)'
            ]
        );

        // Factura Electrónica (F001)
        InvoiceSeries::updateOrCreate(
            [
                'document_type' => Invoice::TYPE_FACTURA,
                'series' => 'F001',
                'environment' => 'production'
            ],
            [
                'current_correlative' => 0,
                'is_active' => true,
                'description' => 'Serie Principal Facturas (Producción)'
            ]
        );

        // Notas de Crédito para Boletas (BC01)
        InvoiceSeries::updateOrCreate(
            [
                'document_type' => Invoice::TYPE_NOTA_CREDITO,
                'series' => 'BC01',
                'environment' => 'production'
            ],
            [
                'current_correlative' => 0,
                'is_active' => true,
                'description' => 'Nota Crédito Boletas (Producción)'
            ]
        );

         // Notas de Crédito para Facturas (FC01)
         InvoiceSeries::updateOrCreate(
            [
                'document_type' => Invoice::TYPE_NOTA_CREDITO,
                'series' => 'FC01',
                'environment' => 'production'
            ],
            [
                'current_correlative' => 0,
                'is_active' => true,
                'description' => 'Nota Crédito Facturas (Producción)'
            ]
        );
    }
}
