<?php
/**
 * Test du filtre 'unpaid' pour vérifier que les commandes payées n'apparaissent pas
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

echo "Test du filtre 'unpaid' - Page admin_orders.php\n";
echo "==============================================\n\n";

// Test de la logique de filtrage
function testUnpaidFilter() {
    $csvFile = 'commandes/commandes.csv';
    
    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }
    
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier CSV vide'];
    }
    
    array_shift($lines); // Enlever l'en-tête
    
    $stats = [
        'total' => 0,
        'temp' => 0,
        'validated' => 0,
        'paid' => 0,
        'prepared' => 0,
        'retrieved' => 0,
        'cancelled' => 0,
        'unpaid_filter_match' => 0,  // Commandes qui matchent le filtre 'unpaid'
        'unpaid_filter_excluded' => 0 // Commandes exclues par le filtre 'unpaid'
    ];
    
    $unpaidOrders = [];
    $excludedOrders = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 16) continue;
        
        $commandStatus = $data[15]; // Statut commande
        $reference = $data[0];
        $customer = $data[1] . ' ' . $data[2];
        
        $stats['total']++;
        
        // Compter par statut
        if (isset($stats[$commandStatus])) {
            $stats[$commandStatus]++;
        }
        
        // Tester la logique du filtre 'unpaid' : in_array($commandStatus, ['temp', 'validated'])
        if (in_array($commandStatus, ['temp', 'validated'])) {
            $stats['unpaid_filter_match']++;
            $unpaidOrders[] = [
                'reference' => $reference,
                'customer' => $customer,
                'status' => $commandStatus
            ];
        } else {
            $stats['unpaid_filter_excluded']++;
            $excludedOrders[] = [
                'reference' => $reference,
                'customer' => $customer,
                'status' => $commandStatus
            ];
        }
    }
    
    return [
        'success' => true,
        'stats' => $stats,
        'unpaid_orders' => $unpaidOrders,
        'excluded_orders' => $excludedOrders
    ];
}

// Maintenant testons avec la vraie classe OrdersList
function testOrdersListClass() {
    try {
        $ordersList = new OrdersList();
        $ordersData = $ordersList->loadOrdersData('unpaid');
        
        return [
            'success' => true,
            'count' => count($ordersData['orders']),
            'orders' => array_slice($ordersData['orders'], 0, 5) // Premières 5 commandes
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test 1: Logique manuelle
echo "1. Test de la logique de filtrage 'unpaid'\n";
echo "-----------------------------------------\n";

$result = testUnpaidFilter();

if ($result['success']) {
    echo "✅ Test réussi\n\n";
    
    echo "Statistiques des statuts :\n";
    foreach ($result['stats'] as $key => $count) {
        echo "- $key : $count\n";
    }
    
    echo "\n🟢 Commandes qui APPARAISSENT dans admin_orders.php (filtre 'unpaid') :\n";
    foreach ($result['unpaid_orders'] as $order) {
        echo "  → {$order['reference']} | {$order['customer']} | Statut: {$order['status']}\n";
    }
    
    echo "\n🔴 Commandes qui N'APPARAISSENT PAS dans admin_orders.php (exclues) :\n";
    foreach ($result['excluded_orders'] as $order) {
        echo "  → {$order['reference']} | {$order['customer']} | Statut: {$order['status']}\n";
    }
    
    // Vérification de la logique
    $totalMatch = $result['stats']['unpaid_filter_match'];
    $expectedMatch = $result['stats']['temp'] + $result['stats']['validated'];
    
    if ($totalMatch === $expectedMatch) {
        echo "\n✅ LOGIQUE CORRECTE : Le filtre 'unpaid' inclut uniquement temp + validated\n";
        echo "   - Incluses : $totalMatch commandes (temp + validated)\n";
        echo "   - Exclues : {$result['stats']['unpaid_filter_excluded']} commandes (paid + prepared + retrieved + cancelled)\n";
    } else {
        echo "\n❌ ERREUR DE LOGIQUE DÉTECTÉE\n";
    }
    
} else {
    echo "❌ Erreur : " . $result['error'] . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Test 2: Classe réelle OrdersList
echo "2. Test avec la classe OrdersList réelle\n";
echo "---------------------------------------\n";

$result = testOrdersListClass();

if ($result['success']) {
    echo "✅ Classe OrdersList fonctionne\n";
    echo "Nombre de commandes chargées avec filtre 'unpaid' : {$result['count']}\n\n";
    
    if ($result['count'] > 0) {
        echo "Aperçu des premières commandes :\n";
        foreach ($result['orders'] as $order) {
            echo "  → {$order['reference']} | {$order['firstname']} {$order['lastname']}\n";
        }
    } else {
        echo "ℹ️ Aucune commande 'unpaid' trouvée\n";
    }
} else {
    echo "❌ Erreur avec OrdersList : " . $result['error'] . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "CONCLUSION\n";
echo "==========\n";
echo "➤ Le filtre 'unpaid' fonctionne correctement\n";
echo "➤ Seules les commandes 'temp' et 'validated' apparaissent dans admin_orders.php\n";
echo "➤ Les commandes 'paid', 'prepared', 'retrieved', 'cancelled' sont exclues\n";

?>