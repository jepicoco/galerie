<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

echo "=== TEST DE FILTRAGE DES COMMANDES ===\n\n";

try {
    $ordersList = new OrdersList();

    // Test filtre unpaid
    echo "1. Test filtre 'unpaid':\n";
    $unpaidData = $ordersList->loadOrdersData('unpaid');
    echo "   Nombre de commandes unpaid: " . count($unpaidData['orders']) . "\n";

    if (!empty($unpaidData['orders'])) {
        $firstUnpaid = $unpaidData['orders'][0];
        echo "   Exemple: {$firstUnpaid['reference']} - {$firstUnpaid['firstname']} {$firstUnpaid['lastname']}\n";
    }

    // Test filtre paid
    echo "\n2. Test filtre 'paid':\n";
    $paidData = $ordersList->loadOrdersData('paid');
    echo "   Nombre de commandes paid: " . count($paidData['orders']) . "\n";

    if (!empty($paidData['orders'])) {
        $firstPaid = $paidData['orders'][0];
        echo "   Exemple: {$firstPaid['reference']} - {$firstPaid['firstname']} {$firstPaid['lastname']}\n";
    }

    // Test filtre all
    echo "\n3. Test filtre 'all':\n";
    $allData = $ordersList->loadOrdersData('all');
    echo "   Nombre total de commandes: " . count($allData['orders']) . "\n";

    // Calcul des statistiques
    echo "\n4. Statistiques:\n";
    $unpaidStats = $ordersList->calculateStats($unpaidData['orders']);
    $paidStats = $ordersList->calculateStats($paidData['orders']);

    echo "   Unpaid - Total: {$unpaidStats['total']}, Photos: {$unpaidStats['total_photos']}, Montant: {$unpaidStats['total_amount']}€\n";
    echo "   Paid - Total: {$paidStats['total']}, Photos: {$paidStats['total_photos']}, Montant: {$paidStats['total_amount']}€\n";

    echo "\n=== TEST TERMINÉ ===\n";

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>