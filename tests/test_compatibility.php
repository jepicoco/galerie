<?php
/**
 * Test de compatibilité avec l'ancien code utilisant Order et OrdersList
 * @version 1.0
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/autoload.php';

echo "=== Test de compatibilité avec l'ancien code ===\n\n";

// Test 1: Simulation de l'utilisation dans admin_orders_handler.php
echo "Test 1: Simulation utilisation admin_orders_handler.php\n";
try {
    // Cette ligne était dans admin_orders_handler.php
    $order = new Order('CMD20250725123456'); // Référence fictive
    
    // Test des méthodes utilisées dans le handler
    if (method_exists($order, 'load')) {
        echo "✅ Méthode load() disponible\n";
    }
    
    if (method_exists($order, 'updatePaymentStatus')) {
        echo "✅ Méthode updatePaymentStatus() disponible\n";
    }
    
    if (method_exists($order, 'markAsExported')) {
        echo "✅ Méthode markAsExported() disponible\n";
    }
    
    if (method_exists($order, 'exportToReglees')) {
        echo "✅ Méthode exportToReglees() disponible\n";
    }
    
    if (method_exists($order, 'exportToPreparer')) {
        echo "✅ Méthode exportToPreparer() disponible\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Simulation de l'utilisation dans admin_orders.php 
echo "Test 2: Simulation utilisation admin_orders.php\n";
try {
    // Cette ligne était dans admin_orders.php
    $ordersList = new OrdersList();
    
    // Test des méthodes utilisées
    if (method_exists($ordersList, 'loadOrdersData')) {
        echo "✅ Méthode loadOrdersData() disponible\n";
        
        // Test réel de chargement
        $result = $ordersList->loadOrdersData(ORDERSLIST_TEMP ?? null);
        echo "✅ Chargement réussi: " . count($result['orders']) . " commandes\n";
    }
    
    if (method_exists($ordersList, 'calculateStats')) {
        echo "✅ Méthode calculateStats() disponible\n";
    }
    
    if (method_exists($ordersList, 'markMultipleAsExported')) {
        echo "✅ Méthode markMultipleAsExported() disponible\n";
    }
    
    if (method_exists($ordersList, 'archiveOldOrders')) {
        echo "✅ Méthode archiveOldOrders() disponible\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test des méthodes héritées de CsvHandler
echo "Test 3: Vérification méthodes héritées de CsvHandler\n";
try {
    $order = new Order();
    
    // Vérifier que les méthodes de base CSV sont disponibles
    $csvMethods = ['read', 'write', 'appendRow', 'updateByValue', 'filter', 'createBackup'];
    
    foreach ($csvMethods as $method) {
        if (method_exists($order, $method)) {
            echo "✅ Méthode CSV '$method' disponible dans Order\n";
        } else {
            echo "❌ Méthode CSV '$method' manquante dans Order\n";
        }
    }
    
    echo "\n";
    
    $ordersList = new OrdersList();
    
    foreach ($csvMethods as $method) {
        if (method_exists($ordersList, $method)) {
            echo "✅ Méthode CSV '$method' disponible dans OrdersList\n";
        } else {
            echo "❌ Méthode CSV '$method' manquante dans OrdersList\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test de la chaîne d'héritage
echo "Test 4: Vérification chaîne d'héritage\n";
try {
    $order = new Order();
    $ordersList = new OrdersList();
    
    echo "Order est une instance de:\n";
    echo "  - Order: " . ($order instanceof Order ? "✅" : "❌") . "\n";
    echo "  - CsvHandler: " . ($order instanceof CsvHandler ? "✅" : "❌") . "\n";
    
    echo "OrdersList est une instance de:\n";
    echo "  - OrdersList: " . ($ordersList instanceof OrdersList ? "✅" : "❌") . "\n";
    echo "  - CsvHandler: " . ($ordersList instanceof CsvHandler ? "✅" : "❌") . "\n";
    
    // Test des classes parentes
    echo "\nHiérarchie des classes:\n";
    echo "Order parent: " . get_parent_class($order) . "\n";
    echo "OrdersList parent: " . get_parent_class($ordersList) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Résultats des tests ===\n";
echo "Si tous les tests sont ✅, la refactorisation est réussie et compatible !\n";
echo "Les classes Order et OrdersList héritent maintenant de CsvHandler tout en conservant leur API existante.\n";

?>