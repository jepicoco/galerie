<?php
/**
 * Test des fonctions de badges unifiées avec OrdersList
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/orders.list.class.php';

echo "=== Test des fonctions de badges unifiées ===\n\n";

// Test 1: Classe OrdersList directement
echo "1. Classe OrdersList (source de vérité):\n";
$ordersList = new OrdersList();
$unpaidFromClass = $ordersList->countPendingPayments();
$retrievalFromClass = $ordersList->countPendingRetrievals();
echo "   countPendingPayments(): " . $unpaidFromClass . " commandes\n";
echo "   countPendingRetrievals(): " . $retrievalFromClass . " commandes\n\n";

// Test 2: Fonctions de functions.php (qui utilisent maintenant OrdersList)
echo "2. Fonctions functions.php (via OrdersList):\n";
$unpaidFromFunctions = countPendingPayments();
$retrievalFromFunctions = countPendingRetrievals();
echo "   countPendingPayments(): " . $unpaidFromFunctions . " commandes\n";
echo "   countPendingRetrievals(): " . $retrievalFromFunctions . " commandes\n\n";

// Test 3: Fonctions de l'API badges (qui utilisent maintenant OrdersList)
echo "3. Fonctions API badges (via OrdersList):\n";

function countUnpaidOrders() {
    $ordersList = new OrdersList();
    return $ordersList->countPendingPayments();
}

function countReadyForPickup() {
    $ordersList = new OrdersList();
    return $ordersList->countPendingRetrievals();
}

function countNewOrders() {
    $ordersList = new OrdersList();
    $allOrdersData = $ordersList->loadOrdersData('all');
    $count = 0;
    $oneDayAgo = time() - 24 * 3600;

    foreach ($allOrdersData['orders'] as $order) {
        $orderDate = $order['created_at'] ?? '';

        if (!empty($orderDate)) {
            $orderTimestamp = strtotime($orderDate);
            if ($orderTimestamp >= $oneDayAgo) {
                $count++;
            }
        }
    }

    return $count;
}

$unpaidFromAPI = countUnpaidOrders();
$retrievalFromAPI = countReadyForPickup();
$newFromAPI = countNewOrders();

echo "   countUnpaidOrders(): " . $unpaidFromAPI . " commandes\n";
echo "   countReadyForPickup(): " . $retrievalFromAPI . " commandes\n";
echo "   countNewOrders(): " . $newFromAPI . " commandes\n\n";

// Test 4: Vérification de cohérence complète
echo "4. Vérification de cohérence complète:\n";
echo "   Classe vs functions.php vs API:\n";
echo "   - Unpaid: " .
    ($unpaidFromClass === $unpaidFromFunctions && $unpaidFromFunctions === $unpaidFromAPI ? "✓ COHÉRENT" : "✗ INCOHÉRENT") .
    " (" . $unpaidFromClass . " / " . $unpaidFromFunctions . " / " . $unpaidFromAPI . ")\n";
echo "   - Retrieval: " .
    ($retrievalFromClass === $retrievalFromFunctions && $retrievalFromFunctions === $retrievalFromAPI ? "✓ COHÉRENT" : "✗ INCOHÉRENT") .
    " (" . $retrievalFromClass . " / " . $retrievalFromFunctions . " / " . $retrievalFromAPI . ")\n\n";

// Test 5: Test des différents filtres OrdersList
echo "5. Test des filtres OrdersList:\n";
$filters = ['all', 'unpaid', 'paid', 'to_retrieve', 'temp', 'validated'];

foreach ($filters as $filter) {
    $data = $ordersList->loadOrdersData($filter);
    $count = count($data['orders']);
    echo "   - $filter: $count commandes\n";
}

echo "\n=== Fin du test ===\n";
?>