<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

echo "=== DEBUG CHARGEMENT UNPAID ===\n\n";

// Activer les logs d'erreur
ini_set('log_errors', 1);
ini_set('error_log', 'debug_unpaid.log');

try {
    echo "1. Création de l'instance OrdersList...\n";
    $ordersList = new OrdersList();

    echo "2. Chargement des données unpaid...\n";
    $unpaidData = $ordersList->loadOrdersData('unpaid');

    echo "3. Résultats:\n";
    echo "   Nombre de commandes unpaid trouvées: " . count($unpaidData['orders']) . "\n";

    if (!empty($unpaidData['orders'])) {
        echo "   Première commande: {$unpaidData['orders'][0]['reference']}\n";
    }

    echo "\n4. Test direct sur quelques lignes connues:\n";
    $csvFile = 'commandes/commandes.csv';
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    array_shift($lines); // Remove header

    $testRefs = ['CMD20250916135436', 'CMD20250918184713'];
    foreach ($lines as $line) {
        $data = str_getcsv($line, ';');
        if (in_array($data[0], $testRefs)) {
            $paymentMode = $data[10] ?? '';
            $commandStatus = $data[15] ?? '';
            echo "   {$data[0]}: Mode='$paymentMode', Statut='$commandStatus'\n";

            // Test de notre logique
            $isUnpaid = ($paymentMode === 'unpaid' || (in_array($commandStatus, ['temp', 'validated']) && $paymentMode !== 'paid'));
            echo "     -> Devrait être unpaid: " . ($isUnpaid ? 'OUI' : 'NON') . "\n";
        }
    }

    echo "\n5. Vérification des logs...\n";
    if (file_exists('debug_unpaid.log')) {
        echo "Logs générés (dernières 10 lignes):\n";
        $logs = file('debug_unpaid.log');
        foreach (array_slice($logs, -10) as $log) {
            echo "   " . trim($log) . "\n";
        }
    } else {
        echo "Aucun log généré.\n";
    }

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEBUG ===\n";
?>