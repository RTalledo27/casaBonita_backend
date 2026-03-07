<?php
try {
    $result = app(\App\Services\LogicwareContractImporter::class)->importContracts();
    echo 'Done. ' . json_encode($result);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
