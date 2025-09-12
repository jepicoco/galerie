<?php
/**
 * Test d'intégrité complète de l'application après refactoring
 * @version 1.0
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';

echo "========================================\n";
echo "  TEST D'INTÉGRITÉ COMPLÈTE - GALA 2025\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$successes = [];

// Test 1: Vérification de l'autoloader
echo "=== TEST 1: AUTOLOADER ===\n";
try {
    require_once 'classes/autoload.php';
    $successes[] = "Autoloader chargé avec succès";
    
    // Test chargement automatique des classes
    if (class_exists('Logger')) {
        $successes[] = "Classe Logger chargée automatiquement";
    } else {
        $errors[] = "Classe Logger non trouvée par l'autoloader";
    }
    
    if (class_exists('CsvHandler')) {
        $successes[] = "Classe CsvHandler chargée automatiquement";
    } else {
        $errors[] = "Classe CsvHandler non trouvée par l'autoloader";
    }
    
    if (class_exists('Order')) {
        $successes[] = "Classe Order chargée automatiquement";
    } else {
        $errors[] = "Classe Order non trouvée par l'autoloader";
    }
    
    if (class_exists('OrdersList')) {
        $successes[] = "Classe OrdersList chargée automatiquement";
    } else {
        $errors[] = "Classe OrdersList non trouvée par l'autoloader";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur autoloader: " . $e->getMessage();
}
echo "\n";

// Test 2: Test du Logger
echo "=== TEST 2: LOGGER ===\n";
try {
    $logger = Logger::getInstance();
    $successes[] = "Logger singleton instancié";
    
    // Test des méthodes
    if (method_exists($logger, 'info')) {
        $logger->info("Test d'intégrité en cours");
        $successes[] = "Méthode Logger::info() fonctionnelle";
    } else {
        $errors[] = "Méthode Logger::info() manquante";
    }
    
    if (method_exists($logger, 'error')) {
        $successes[] = "Méthode Logger::error() disponible";
    } else {
        $errors[] = "Méthode Logger::error() manquante";
    }
    
    if (method_exists($logger, 'adminAction')) {
        $successes[] = "Méthode Logger::adminAction() disponible";
    } else {
        $errors[] = "Méthode Logger::adminAction() manquante";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur Logger: " . $e->getMessage();
}
echo "\n";

// Test 3: Test CsvHandler
echo "=== TEST 3: CSVHANDLER ===\n";
try {
    $csv = new CsvHandler();
    $successes[] = "CsvHandler instancié";
    
    // Test des méthodes principales
    $testMethods = ['read', 'write', 'appendRow', 'updateByValue', 'filter', 'createBackup'];
    foreach ($testMethods as $method) {
        if (method_exists($csv, $method)) {
            $successes[] = "Méthode CsvHandler::$method() disponible";
        } else {
            $errors[] = "Méthode CsvHandler::$method() manquante";
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur CsvHandler: " . $e->getMessage();
}
echo "\n";

// Test 4: Test héritage Order
echo "=== TEST 4: HÉRITAGE ORDER ===\n";
try {
    $order = new Order();
    $successes[] = "Classe Order instanciée";
    
    // Vérifier l'héritage
    if ($order instanceof CsvHandler) {
        $successes[] = "Order hérite correctement de CsvHandler";
    } else {
        $errors[] = "Order n'hérite pas de CsvHandler";
    }
    
    // Test des méthodes spécifiques
    $orderMethods = ['generateReference', 'load', 'updatePaymentStatus', 'markAsExported', 'exportToReglees', 'exportToPreparer'];
    foreach ($orderMethods as $method) {
        if (method_exists($order, $method)) {
            $successes[] = "Méthode Order::$method() disponible";
        } else {
            $errors[] = "Méthode Order::$method() manquante";
        }
    }
    
    // Test des méthodes héritées
    $inheritedMethods = ['read', 'write', 'updateByValue'];
    foreach ($inheritedMethods as $method) {
        if (method_exists($order, $method)) {
            $successes[] = "Méthode héritée Order::$method() accessible";
        } else {
            $errors[] = "Méthode héritée Order::$method() non accessible";
        }
    }
    
    // Test fonctionnel
    $ref = $order->generateReference();
    if (preg_match('/CMD\d{14}/', $ref)) {
        $successes[] = "Génération de référence fonctionnelle: $ref";
    } else {
        $errors[] = "Génération de référence défaillante: $ref";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur Order: " . $e->getMessage();
}
echo "\n";

// Test 5: Test héritage OrdersList
echo "=== TEST 5: HÉRITAGE ORDERSLIST ===\n";
try {
    $ordersList = new OrdersList();
    $successes[] = "Classe OrdersList instanciée";
    
    // Vérifier l'héritage
    if ($ordersList instanceof CsvHandler) {
        $successes[] = "OrdersList hérite correctement de CsvHandler";
    } else {
        $errors[] = "OrdersList n'hérite pas de CsvHandler";
    }
    
    // Test des méthodes spécifiques
    $ordersListMethods = ['loadOrdersData', 'calculateStats', 'markMultipleAsExported', 'archiveOldOrders'];
    foreach ($ordersListMethods as $method) {
        if (method_exists($ordersList, $method)) {
            $successes[] = "Méthode OrdersList::$method() disponible";
        } else {
            $errors[] = "Méthode OrdersList::$method() manquante";
        }
    }
    
    // Test des méthodes héritées
    $inheritedMethods = ['read', 'write', 'filter'];
    foreach ($inheritedMethods as $method) {
        if (method_exists($ordersList, $method)) {
            $successes[] = "Méthode héritée OrdersList::$method() accessible";
        } else {
            $errors[] = "Méthode héritée OrdersList::$method() non accessible";
        }
    }
    
    // Test fonctionnel (si fichier commandes existe)
    if (file_exists('commandes/commandes.csv')) {
        $data = $ordersList->loadOrdersData();
        $successes[] = "Chargement commandes: " . count($data['orders']) . " trouvées";
        
        $stats = $ordersList->calculateStats($data['orders']);
        $successes[] = "Calcul statistiques: " . $stats['total_orders'] . " commandes";
    } else {
        $warnings[] = "Fichier commandes/commandes.csv non trouvé (normal si pas de commandes)";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur OrdersList: " . $e->getMessage();
}
echo "\n";

// Test 6: Vérification des fichiers essentiels
echo "=== TEST 6: FICHIERS ESSENTIELS ===\n";
$essentialFiles = [
    'config.php' => 'Configuration principale',
    'index.php' => 'Page d\'accueil',
    'admin.php' => 'Interface admin',
    'classes/autoload.php' => 'Autoloader',
    'classes/csv.class.php' => 'Classe CSV',
    'classes/order.class.php' => 'Classe Order',
    'classes/orders.liste.class.php' => 'Classe OrdersList',
    'classes/logger.class.php' => 'Classe Logger'
];

foreach ($essentialFiles as $file => $description) {
    if (file_exists($file)) {
        $successes[] = "Fichier $description présent";
    } else {
        $errors[] = "Fichier $description manquant: $file";
    }
}
echo "\n";

// Test 7: Vérification des dossiers
echo "=== TEST 7: STRUCTURE DOSSIERS ===\n";
$essentialDirs = [
    'classes' => 'Classes PHP',
    'data' => 'Données JSON',
    'logs' => 'Fichiers de logs',
    'photos' => 'Photos galerie',
    'commandes' => 'Fichiers commandes'
];

foreach ($essentialDirs as $dir => $description) {
    if (is_dir($dir)) {
        $successes[] = "Dossier $description présent";
        if (is_writable($dir)) {
            $successes[] = "Dossier $description accessible en écriture";
        } else {
            $warnings[] = "Dossier $description non accessible en écriture";
        }
    } else {
        $warnings[] = "Dossier $description manquant: $dir";
    }
}
echo "\n";

// Test 8: Test d'intégration avec l'ancien code
echo "=== TEST 8: COMPATIBILITÉ ANCIEN CODE ===\n";
try {
    // Simulation d'utilisation dans admin_orders_handler.php
    $order = new Order('CMD20250101000000');
    if (method_exists($order, 'updatePaymentStatus')) {
        $successes[] = "Interface Order compatible avec admin_orders_handler.php";
    } else {
        $errors[] = "Interface Order non compatible avec admin_orders_handler.php";
    }
    
    // Simulation d'utilisation dans admin_orders.php
    $ordersList = new OrdersList();
    if (method_exists($ordersList, 'loadOrdersData')) {
        $successes[] = "Interface OrdersList compatible avec admin_orders.php";
    } else {
        $errors[] = "Interface OrdersList non compatible avec admin_orders.php";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur compatibilité: " . $e->getMessage();
}
echo "\n";

// Résumé final
echo "========================================\n";
echo "  RÉSUMÉ DES TESTS\n";
echo "========================================\n\n";

echo "✅ SUCCÈS (" . count($successes) . "):\n";
foreach ($successes as $success) {
    echo "  ✅ $success\n";
}
echo "\n";

if (!empty($warnings)) {
    echo "⚠️ AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠️ $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  ❌ $error\n";
    }
    echo "\n";
}

// Évaluation globale
$totalTests = count($successes) + count($warnings) + count($errors);
$successRate = count($successes) / $totalTests * 100;

echo "========================================\n";
echo "  ÉVALUATION GLOBALE\n";
echo "========================================\n";
echo "Taux de réussite: " . round($successRate, 1) . "%\n";

if ($successRate >= 95) {
    echo "🎉 EXCELLENT: L'application est parfaitement intègre\n";
} elseif ($successRate >= 85) {
    echo "✅ BON: L'application fonctionne correctement avec quelques améliorations possibles\n";
} elseif ($successRate >= 70) {
    echo "⚠️ MOYEN: L'application fonctionne mais nécessite des corrections\n";
} else {
    echo "❌ CRITIQUE: L'application présente des problèmes majeurs\n";
}

echo "\n";

$logger->info("Test d'intégrité terminé", [
    'successes' => count($successes),
    'warnings' => count($warnings), 
    'errors' => count($errors),
    'success_rate' => round($successRate, 1)
]);

?>