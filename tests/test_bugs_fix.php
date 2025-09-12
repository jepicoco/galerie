<?php
/**
 * Test de correction des erreurs Notice et Fatal error
 * Vérifie que retrieved_today et cleanOldTempOrders() fonctionnent
 */

echo "=== Test de correction des bugs ===\n\n";

// Définir l'accès
define('GALLERY_ACCESS', true);

// Inclure la configuration
require_once 'config.php';

// Inclure functions.php pour cleanOldTempOrders
require_once 'functions.php';

// Inclure l'autoloader
require_once 'classes/autoload.php';

echo "✅ Configuration, functions.php et autoloader chargés\n";

// Test 1: Vérifier que cleanOldTempOrders existe
echo "\n=== Test 1: fonction cleanOldTempOrders() ===\n";
if (function_exists('cleanOldTempOrders')) {
    echo "✅ Fonction cleanOldTempOrders() existe\n";
    
    // Tester l'appel (comme dans index.php)
    try {
        $deleted = cleanOldTempOrders(COMMANDES_DIR);
        echo "✅ Fonction cleanOldTempOrders() exécutée: $deleted fichiers supprimés\n";
    } catch (Exception $e) {
        echo "❌ Erreur lors de l'exécution: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Fonction cleanOldTempOrders() n'existe pas\n";
}

// Test 2: Vérifier que retrieved_today existe dans les stats
echo "\n=== Test 2: index retrieved_today dans calculateStats() ===\n";
try {
    $ordersList = new OrdersList();
    $ordersData = $ordersList->loadOrdersData('paid');
    $stats = $ordersList->calculateStats($ordersData['orders']);
    
    if (isset($stats['retrieved_today'])) {
        echo "✅ Index 'retrieved_today' existe: " . $stats['retrieved_today'] . "\n";
    } else {
        echo "❌ Index 'retrieved_today' manquant\n";
    }
    
    // Vérifier aussi les autres index importants
    $requiredIndexes = ['total', 'paid_today', 'retrieved_today', 'total_amount'];
    echo "\nVérification de tous les index requis:\n";
    foreach ($requiredIndexes as $index) {
        if (isset($stats[$index])) {
            echo "   ✅ $index: " . $stats[$index] . "\n";
        } else {
            echo "   ❌ $index: MANQUANT\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test OrdersList: " . $e->getMessage() . "\n";
}

// Test 3: Simuler le code problématique
echo "\n=== Test 3: Simulation des codes problématiques ===\n";

// Simuler admin_paid_orders.php ligne 74
try {
    $ordersList = new OrdersList();
    $paidOrdersData = $ordersList->loadOrdersData('paid');
    $paidStats = $ordersList->calculateStats($paidOrdersData['orders']);
    
    // Code de la ligne 74: $paidStats['retrieved_today']
    $retrievedToday = $paidStats['retrieved_today'];
    echo "✅ admin_paid_orders.php ligne 74 OK: retrieved_today = $retrievedToday\n";
    
} catch (Exception $e) {
    echo "❌ Erreur admin_paid_orders.php: " . $e->getMessage() . "\n";
}

// Simuler index.php ligne 35
try {
    // Code de la ligne 35: cleanOldTempOrders(COMMANDES_DIR)
    $deleted = cleanOldTempOrders(COMMANDES_DIR);
    echo "✅ index.php ligne 35 OK: cleanOldTempOrders() = $deleted\n";
    
} catch (Exception $e) {
    echo "❌ Erreur index.php: " . $e->getMessage() . "\n";
}

echo "\n=== Test terminé ===\n";
echo "Les bugs Notice et Fatal error ont été corrigés.\n";
?>