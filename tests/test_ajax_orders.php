<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'admin_orders_handler.php';

// Simuler une requête POST pour tester l'endpoint
$_POST = [
    'action' => 'get_orders',
    'status' => 'unpaid'
];

echo "=== TEST ENDPOINT AJAX ===\n\n";

echo "1. Test avec status = 'unpaid':\n";
$result = getFilteredOrders('unpaid');
echo "   Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "   Count: " . ($result['count'] ?? 0) . "\n";

if (isset($result['tab_stats'])) {
    echo "   Unpaid count: " . $result['tab_stats']['unpaid']['count'] . "\n";
    echo "   Paid count: " . $result['tab_stats']['paid']['count'] . "\n";
}

echo "\n2. Test avec status = 'paid':\n";
$result = getFilteredOrders('paid');
echo "   Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "   Count: " . ($result['count'] ?? 0) . "\n";

if (isset($result['error'])) {
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n=== FIN TEST ===\n";
?>