<?php
try {
    $api = app(App\Services\LogicwareApiService::class);
    $sales = $api->getSales('2024-01-01', '2027-01-01', false);
    echo "Success: \n";
    echo substr(json_encode($sales), 0, 500);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
