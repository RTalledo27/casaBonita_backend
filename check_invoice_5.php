<?php

use Modules\Accounting\Models\Invoice;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$invoice = Invoice::find(5);

if ($invoice) {
    echo "ID: " . $invoice->invoice_id . "\n";
    echo "Status: " . $invoice->sunat_status . "\n";
    echo "Description: " . $invoice->cdr_description . "\n";
    echo "PDF Path: " . ($invoice->pdf_path ?? 'NULL') . "\n";
} else {
    echo "Invoice #5 not found.\n";
}
