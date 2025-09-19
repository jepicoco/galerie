<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

echo "=== TEST CORRECTION ORDERS.LISTE.CLASS.PHP ===\n\n";

try {
    echo "1. Test avec la classe OrdersList (qui utilise orders.liste.class.php):\n";
    $ordersList = new OrdersList();

    // Test filtre unpaid
    $unpaidData = $ordersList->loadOrdersData('unpaid');
    echo "   Commandes unpaid trouvées: " . count($unpaidData['orders']) . "\n";

    // Test filtre paid
    $paidData = $ordersList->loadOrdersData('paid');
    echo "   Commandes paid trouvées: " . count($paidData['orders']) . "\n";

    // Test avec référence connue
    if (!empty($unpaidData['orders'])) {
        $firstUnpaid = $unpaidData['orders'][0];
        echo "   Première commande unpaid: {$firstUnpaid['reference']}\n";
    }

    if (!empty($paidData['orders'])) {
        $firstPaid = $paidData['orders'][0];
        echo "   Première commande paid: {$firstPaid['reference']}\n";
    }

    echo "\n2. Calcul des statistiques d'onglets:\n";
    $unpaidStats = $ordersList->calculateStats($unpaidData['orders']);
    $paidStats = $ordersList->calculateStats($paidData['orders']);

    $tabStats = [
        'unpaid' => [
            'count' => count($unpaidData['orders']),
            'amount' => $unpaidStats['total_amount']
        ],
        'paid' => [
            'count' => count($paidData['orders']),
            'amount' => $paidStats['total_amount']
        ]
    ];

    echo "   Badge 'En attente de règlement': {$tabStats['unpaid']['count']}\n";
    echo "   Badge 'Réglées': {$tabStats['paid']['count']}\n";

    echo "\n=== RÉSULTAT ===\n";
    if ($tabStats['unpaid']['count'] > 0) {
        echo "✅ CORRECTION RÉUSSIE ! Les commandes unpaid sont maintenant détectées.\n";
    } else {
        echo "❌ Problème persistant - Aucune commande unpaid trouvée.\n";
    }

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";
?>