<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

use Modules\HumanResources\app\Models\Commission;

// Debug commission ID 34
$commission = Commission::find(34);

if (!$commission) {
    echo "Commission ID 34 not found\n";
    exit;
}

echo "=== COMMISSION ID 34 DEBUG ===\n";
echo "Commission ID: " . $commission->commission_id . "\n";
echo "Payment Part: " . ($commission->payment_part ?? 'NULL') . "\n";
echo "Parent Commission ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
echo "Status: " . $commission->status . "\n";
echo "Contract ID: " . $commission->contract_id . "\n";
echo "Amount: " . $commission->amount . "\n";

// Check if this is a parent commission (has children)
$childCommissions = Commission::where('parent_commission_id', 34)->get();

if ($childCommissions->count() > 0) {
    echo "\n=== CHILD COMMISSIONS ===\n";
    foreach ($childCommissions as $child) {
        echo "Child ID: " . $child->commission_id . "\n";
        echo "  Payment Part: " . ($child->payment_part ?? 'NULL') . "\n";
        echo "  Status: " . $child->status . "\n";
        echo "  Amount: " . $child->amount . "\n";
        echo "  Parent ID: " . $child->parent_commission_id . "\n";
        echo "  ---\n";
    }
} else {
    echo "\nNo child commissions found.\n";
}

// Check if this is a child commission (has parent)
if ($commission->parent_commission_id) {
    echo "\n=== PARENT COMMISSION ===\n";
    $parent = Commission::find($commission->parent_commission_id);
    if ($parent) {
        echo "Parent ID: " . $parent->commission_id . "\n";
        echo "  Payment Part: " . ($parent->payment_part ?? 'NULL') . "\n";
        echo "  Status: " . $parent->status . "\n";
        echo "  Amount: " . $parent->amount . "\n";
        
        // Get all siblings
        $siblings = Commission::where('parent_commission_id', $parent->commission_id)->get();
        echo "\n=== ALL SIBLINGS (INCLUDING SELF) ===\n";
        foreach ($siblings as $sibling) {
            echo "Sibling ID: " . $sibling->commission_id . "\n";
            echo "  Payment Part: " . ($sibling->payment_part ?? 'NULL') . "\n";
            echo "  Status: " . $sibling->status . "\n";
            echo "  Amount: " . $sibling->amount . "\n";
            echo "  ---\n";
        }
    }
}

echo "\n=== ANALYSIS ===\n";
if (is_null($commission->payment_part) && is_null($commission->parent_commission_id)) {
    echo "This is a PARENT commission (payment_part = null, parent_commission_id = null)\n";
    echo "When requesting payment_part = 2, the system should look for a child with payment_part = 2\n";
} elseif (!is_null($commission->payment_part)) {
    echo "This is a CHILD commission with payment_part = " . $commission->payment_part . "\n";
    echo "The system will validate that the requested payment_part matches this value\n";
} else {
    echo "This commission has an unusual configuration\n";
}

?>