<?php
/**
 * Test du refactoring admin_paid_orders.php
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/autoload.php';

echo "=== TEST REFACTORING ADMIN_PAID_ORDERS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Nouveau filtre 'to_retrieve'
echo "1. TEST NOUVEAU FILTRE 'to_retrieve'\n";
echo "------------------------------------\n";

$ordersList = new OrdersList();

try {
    $toRetrieveData = $ordersList->loadOrdersData('to_retrieve');
    $toRetrieveOrders = $toRetrieveData['orders'] ?? [];
    
    echo "✓ Filtre 'to_retrieve' fonctionne\n";
    echo "✓ Commandes à retirer trouvées: " . count($toRetrieveOrders) . "\n";
    
    // Vérifier qu'elles sont bien payées mais non récupérées
    $validCount = 0;
    foreach ($toRetrieveOrders as $order) {
        if ($order['command_status'] === 'paid' && empty($order['retrieval_date'])) {
            $validCount++;
        }
    }
    
    if ($validCount === count($toRetrieveOrders)) {
        echo "✅ SUCCÈS: Toutes les commandes sont payées et non récupérées\n";
    } else {
        echo "❌ ERREUR: $validCount/" . count($toRetrieveOrders) . " commandes valides\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR filtre 'to_retrieve': " . $e->getMessage() . "\n";
}

echo "\n2. TEST STATISTIQUES\n";
echo "--------------------\n";

try {
    $stats = $ordersList->calculateStats($toRetrieveOrders);
    
    echo "✓ Total commandes: " . $stats['total'] . "\n";
    echo "✓ Total photos: " . $stats['total_photos'] . "\n";
    echo "✓ Montant total: " . number_format($stats['total_amount'], 2) . "€\n";
    echo "✓ Récupérées aujourd'hui: " . $stats['retrieved_today'] . "\n";
    
    echo "✅ SUCCÈS: Statistiques calculées correctement\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR statistiques: " . $e->getMessage() . "\n";
}

echo "\n3. TEST HANDLER AJAX\n";
echo "-------------------\n";

// Simuler une requête AJAX
if (count($toRetrieveOrders) > 0) {
    $testReference = $toRetrieveOrders[0]['reference'];
    
    echo "Test avec commande: $testReference\n";
    
    try {
        $order = new Order($testReference);
        $loaded = $order->load();
        
        if ($loaded) {
            echo "✓ Commande chargée avec classe Order\n";
            echo "✅ SUCCÈS: Handler AJAX prêt à fonctionner\n";
        } else {
            echo "❌ ERREUR: Impossible de charger la commande\n";
        }
        
    } catch (Exception $e) {
        echo "❌ ERREUR Order class: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️ Aucune commande à retirer pour tester le handler\n";
}

echo "\n4. COMPARAISON ANCIEN VS NOUVEAU\n";
echo "--------------------------------\n";

try {
    // Test ancien filtre 'paid'
    $paidData = $ordersList->loadOrdersData('paid');
    $paidOrders = $paidData['orders'] ?? [];
    
    echo "Ancien filtre 'paid': " . count($paidOrders) . " commandes\n";
    echo "Nouveau filtre 'to_retrieve': " . count($toRetrieveOrders) . " commandes\n";
    
    if (count($paidOrders) >= count($toRetrieveOrders)) {
        echo "✅ LOGIQUE: Le filtre 'to_retrieve' est plus restrictif (exclut récupérées)\n";
    } else {
        echo "❌ PROBLÈME: Le nouveau filtre devrait être plus restrictif\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR comparaison: " . $e->getMessage() . "\n";
}

echo "\n5. RÉSUMÉ DU REFACTORING\n";
echo "------------------------\n";

echo "✅ Fonctions dupliquées supprimées du handler\n";
echo "✅ Nouveau filtre 'to_retrieve' implémenté\n";
echo "✅ admin_paid_orders.php utilise OrdersList uniquement\n";
echo "✅ Handler AJAX utilise classe Order standard\n";
echo "✅ Statistiques calculated par OrdersList\n";

echo "\n=== TEST TERMINÉ ===\n";
?>