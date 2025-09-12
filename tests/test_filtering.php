<?php
/**
 * Test du filtrage des commandes payées
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/orders.list.class.php';

echo "=== TEST FILTRAGE DES COMMANDES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Créer une instance de OrdersList avec filtre 'unpaid'
$ordersList = new OrdersList();
$ordersList->setFilter('unpaid');

echo "1. CONFIGURATION DU FILTRE\n";
echo "--------------------------\n";
echo "Filtre appliqué: unpaid\n";
echo "Statuts inclus: " . implode(', ', $ordersList->getIncludedStatuses()) . "\n\n";

echo "2. CHARGEMENT DES COMMANDES\n";
echo "---------------------------\n";
$result = $ordersList->loadOrders();

if (!$result) {
    echo "❌ ERREUR: Impossible de charger les commandes\n";
    exit(1);
}

$orders = $ordersList->getOrders();
$totalOrders = count($orders);

echo "✓ Commandes chargées: $totalOrders\n\n";

echo "3. ANALYSE DES STATUTS\n";
echo "----------------------\n";

$statusCount = [];
foreach ($orders as $order) {
    $status = $order['command_status'] ?? 'unknown';
    $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
}

foreach ($statusCount as $status => $count) {
    echo "- $status: $count commande(s)\n";
}

echo "\n4. VÉRIFICATION CRITIQUE\n";
echo "------------------------\n";

$paidFound = false;
foreach ($orders as $order) {
    if ($order['command_status'] === 'paid') {
        $paidFound = true;
        echo "❌ PROBLÈME: Commande payée trouvée (REF: {$order['reference']})\n";
    }
}

if (!$paidFound) {
    echo "✅ SUCCÈS: Aucune commande payée trouvée dans le filtre 'unpaid'\n";
} else {
    echo "❌ ÉCHEC: Des commandes payées sont encore visibles\n";
}

echo "\n5. DÉTAILS DES COMMANDES VISIBLE\n";
echo "---------------------------------\n";

$maxDisplay = 5;
$displayed = 0;

foreach ($orders as $order) {
    if ($displayed >= $maxDisplay) {
        echo "... (+" . ($totalOrders - $maxDisplay) . " autres commandes)\n";
        break;
    }
    
    echo "REF: {$order['reference']}\n";
    echo "  Statut: {$order['command_status']}\n";
    echo "  Paiement: {$order['payment_mode']}\n";
    echo "  Nom: {$order['lastname']} {$order['firstname']}\n";
    echo "\n";
    $displayed++;
}

echo "=== TEST TERMINÉ ===\n";
?>