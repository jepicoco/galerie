<?php
/**
 * Test rapide du filtre 'to_retrieve'
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/autoload.php';

echo "=== TEST FILTRE TO_RETRIEVE ===\n";

$csvFile = 'commandes/commandes.csv';

if (!file_exists($csvFile)) {
    echo "❌ Fichier CSV non trouvé\n";
    exit;
}

// Analyse manuelle du CSV pour comprendre les données
$lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = array_shift($lines);

echo "Structure CSV:\n$header\n\n";

$paidCount = 0;
$paidNotRetrievedCount = 0;
$retrievedCount = 0;

foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    
    $data = str_getcsv($line, ';');
    if (count($data) < 16) continue;
    
    $commandStatus = $data[15] ?? '';
    $retrievalDate = $data[14] ?? '';
    
    if ($commandStatus === 'paid') {
        $paidCount++;
        if (empty($retrievalDate)) {
            $paidNotRetrievedCount++;
            // Afficher quelques exemples
            if ($paidNotRetrievedCount <= 3) {
                echo "Exemple à retirer: {$data[0]} - {$data[2]} {$data[1]}\n";
            }
        }
    }
    
    if ($commandStatus === 'retrieved') {
        $retrievedCount++;
    }
}

echo "\nRésultats manuels:\n";
echo "- Commandes paid: $paidCount\n";
echo "- Commandes paid non récupérées (to_retrieve): $paidNotRetrievedCount\n";
echo "- Commandes retrieved: $retrievedCount\n\n";

// Test avec OrdersList
echo "Test avec OrdersList:\n";

$ordersList = new OrdersList();

$paidData = $ordersList->loadOrdersData('paid');
$toRetrieveData = $ordersList->loadOrdersData('to_retrieve');

echo "- Filtre 'paid': " . count($paidData['orders']) . " commandes\n";
echo "- Filtre 'to_retrieve': " . count($toRetrieveData['orders']) . " commandes\n";

if (count($toRetrieveData['orders']) == $paidNotRetrievedCount) {
    echo "✅ SUCCÈS: Le filtre 'to_retrieve' fonctionne correctement\n";
} else {
    echo "❌ ERREUR: Différence entre analyse manuelle et filtre\n";
    echo "   Attendu: $paidNotRetrievedCount, Obtenu: " . count($toRetrieveData['orders']) . "\n";
}

echo "\n=== FIN TEST ===\n";
?>