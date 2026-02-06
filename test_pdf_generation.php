<?php

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\SunatService;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $invoice = Invoice::find(1); // Invoice ID 1 from user logs

    if (!$invoice) {
        echo "Invoice #1 not found.\n";
        exit(1);
    }

    echo "Invoice found: " . $invoice->full_number . "\n";
    echo "Current PDF path in DB: " . ($invoice->pdf_path ?? 'NULL') . "\n";

    $service = new SunatService();
    // This method should now trigger ensurePdfExists / generatePdf if missing
    $path = $service->getPdfPath($invoice);

    if ($path) {
        echo "SUCCESS: PDF Path returned: " . $path . "\n";
        if (file_exists($path)) {
            echo "SUCCESS: File exists on disk.\n";
            echo "File size: " . filesize($path) . " bytes\n";
            echo "Header: " . file_get_contents($path, false, null, 0, 4) . "\n";
        } else {
            echo "ERROR: File path returned but file does not exist.\n";
        }
    } else {
        echo "ERROR: getPdfPath returned NULL.\n";
    }

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
