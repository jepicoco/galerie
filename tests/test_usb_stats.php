<?php
/**
 * Test pour vérifier les statistiques des clés USB
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/orders.list.class.php';

echo "=== Test des statistiques clés USB ===\n\n";

$ordersList = new OrdersList();

// Test avec toutes les commandes
$allOrdersData = $ordersList->loadOrdersData('all');
$allStats = $ordersList->calculateStats($allOrdersData['orders']);

echo "Statistiques globales :\n";
echo "- Total commandes : " . $allStats['total_orders'] . "\n";
echo "- Total photos : " . $allStats['total_photos'] . "\n";
echo "- Total clés USB : " . $allStats['total_usb_keys'] . "\n";
echo "- Montant total : " . number_format($allStats['total_amount'], 2) . "€\n\n";

// Vérifier les données brutes pour debug
echo "=== Détail des commandes avec clés USB ===\n";
foreach ($allOrdersData['orders'] as $order) {
    if (isset($order['photos'])) {
        foreach ($order['photos'] as $photo) {
            if ($photo['activity_key'] === 'Film du Gala' && strpos($photo['name'], 'CLE USB') !== false) {
                echo "Commande " . $order['reference'] . " - " . $photo['name'] . " (Quantité: " . $photo['quantity'] . ")\n";
            }
        }
    }
}

echo "\n=== Test terminé ===\n";
?>