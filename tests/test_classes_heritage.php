<?php
/**
 * Test de l'héritage des classes Order et OrdersList vers CsvHandler
 * @version 1.0
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/autoload.php';

echo "=== Test de l'héritage des classes Order et OrdersList ===\n\n";

// Test 1: Vérifier que la classe Order hérite bien de CsvHandler
try {
    $order = new Order();
    echo "✅ Classe Order instanciée avec succès\n";
    
    // Vérifier l'héritage
    if ($order instanceof CsvHandler) {
        echo "✅ Order hérite bien de CsvHandler\n";
    } else {
        echo "❌ Order n'hérite pas de CsvHandler\n";
    }
    
    // Tester l'accès aux méthodes héritées
    if (method_exists($order, 'read')) {
        echo "✅ Méthode 'read' accessible dans Order\n";
    } else {
        echo "❌ Méthode 'read' non accessible dans Order\n";
    }
    
    if (method_exists($order, 'write')) {
        echo "✅ Méthode 'write' accessible dans Order\n";
    } else {
        echo "❌ Méthode 'write' non accessible dans Order\n";
    }
    
    if (method_exists($order, 'updateByValue')) {
        echo "✅ Méthode 'updateByValue' accessible dans Order\n";
    } else {
        echo "❌ Méthode 'updateByValue' non accessible dans Order\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors de l'instanciation de Order: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Vérifier que la classe OrdersList hérite bien de CsvHandler
try {
    $ordersList = new OrdersList();
    echo "✅ Classe OrdersList instanciée avec succès\n";
    
    // Vérifier l'héritage
    if ($ordersList instanceof CsvHandler) {
        echo "✅ OrdersList hérite bien de CsvHandler\n";
    } else {
        echo "❌ OrdersList n'hérite pas de CsvHandler\n";
    }
    
    // Tester l'accès aux méthodes héritées
    if (method_exists($ordersList, 'read')) {
        echo "✅ Méthode 'read' accessible dans OrdersList\n";
    } else {
        echo "❌ Méthode 'read' non accessible dans OrdersList\n";
    }
    
    if (method_exists($ordersList, 'write')) {
        echo "✅ Méthode 'write' accessible dans OrdersList\n";
    } else {
        echo "❌ Méthode 'write' non accessible dans OrdersList\n";
    }
    
    if (method_exists($ordersList, 'filter')) {
        echo "✅ Méthode 'filter' accessible dans OrdersList\n";
    } else {
        echo "❌ Méthode 'filter' non accessible dans OrdersList\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors de l'instanciation de OrdersList: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Vérifier que les méthodes fonctionnent
echo "=== Test des méthodes ===\n";

try {
    // Test avec une commande existante (si elle existe)
    $order = new Order();
    $order->generateReference();
    echo "✅ Génération de référence: " . $order->getReference() . "\n";
    
    // Test de la date de création
    $creationDate = $order->getCreationDate();
    echo "✅ Date de création: " . $creationDate . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test des méthodes Order: " . $e->getMessage() . "\n";
}

try {
    // Test de chargement des données
    $ordersList = new OrdersList();
    $data = $ordersList->loadOrdersData();
    echo "✅ Chargement des données: " . count($data['orders']) . " commande(s) trouvée(s)\n";
    
    $stats = $ordersList->calculateStats($data['orders']);
    echo "✅ Calcul des statistiques: " . $stats['total_orders'] . " commandes, " . $stats['total_amount'] . "€\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test des méthodes OrdersList: " . $e->getMessage() . "\n";
}

echo "\n=== Fin des tests ===\n";
echo "Si tous les tests sont ✅, l'héritage fonctionne correctement !\n";

?>