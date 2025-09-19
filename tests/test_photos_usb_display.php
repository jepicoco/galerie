<?php
/**
 * Test pour vérifier l'affichage séparé photos/clés USB
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/orders.list.class.php';

echo "=== Test affichage séparé photos/clés USB ===\n\n";

$ordersList = new OrdersList();

// Test avec les commandes non payées
$unpaidData = $ordersList->loadOrdersData('unpaid');

echo "Commandes non payées :\n";
foreach ($unpaidData['orders'] as $order) {
    echo "Commande " . $order['reference'] . " :\n";
    echo "  - Photos normales : " . $order['photos_count'] . "\n";
    echo "  - Clés USB : " . $order['usb_keys_count'] . "\n";
    echo "  - Total photos : " . $order['total_photos'] . "\n";

    // Simuler l'affichage PHP
    $parts = [];
    if ($order['photos_count'] > 0) {
        $parts[] = $order['photos_count'] . ' photo' . ($order['photos_count'] > 1 ? 's' : '');
    }
    if ($order['usb_keys_count'] > 0) {
        $parts[] = $order['usb_keys_count'] . ' clé' . ($order['usb_keys_count'] > 1 ? 's' : '') . ' USB';
    }
    echo "  - Affichage : " . implode(' + ', $parts) . "\n\n";
}

echo "\nStatistiques globales :\n";
$allData = $ordersList->loadOrdersData('all');
$stats = $ordersList->calculateStats($allData['orders']);
echo "- Total photos : " . $stats['total_photos'] . "\n";
echo "- Total clés USB : " . $stats['total_usb_keys'] . "\n";

echo "\n=== Test terminé ===\n";
?>