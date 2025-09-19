<?php
/**
 * Script de test pour vérifier le parsing des dates des commandes
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

echo "<h2>Test de parsing des dates de commandes</h2>\n";

// Les références problématiques identifiées
$problematicRefs = [
    'CMD20250916141686',
    'CMD20250916144593',
    'CMD20250916155379'
];

// Références qui fonctionnent pour comparaison
$workingRefs = [
    'CMD20250915140648',
    'CMD20250916135436',
    'CMD20250916135423',
    'CMD20250916141326'
];

echo "<h3>1. Test des références problématiques</h3>\n";
foreach ($problematicRefs as $ref) {
    echo "<h4>Référence: $ref</h4>\n";
    testOrderReference($ref);
}

echo "<h3>2. Test des références qui fonctionnent</h3>\n";
foreach ($workingRefs as $ref) {
    echo "<h4>Référence: $ref</h4>\n";
    testOrderReference($ref);
}

echo "<h3>3. Vérification des données CSV brutes</h3>\n";
checkCsvData();

echo "<h3>4. Test de la classe OrdersList</h3>\n";
testOrdersList();

function testOrderReference($reference) {
    echo "Référence: $reference<br>\n";

    // Test avec regex
    if (preg_match('/CMD(\d{8})(\d{6})/', $reference, $matches)) {
        $date = $matches[1];
        $time = $matches[2];

        echo "Date extraite: $date<br>\n";
        echo "Heure extraite: $time<br>\n";

        $year = substr($date, 0, 4);
        $month = substr($date, 4, 2);
        $day = substr($date, 6, 2);
        $hour = substr($time, 0, 2);
        $minute = substr($time, 2, 2);
        $second = substr($time, 4, 2);

        echo "Année: $year, Mois: $month, Jour: $day<br>\n";
        echo "Heure: $hour, Minute: $minute, Seconde: $second<br>\n";

        // Validation
        echo "Validation heure (0-23): " . ($hour >= 0 && $hour <= 23 ? "✓" : "✗") . "<br>\n";
        echo "Validation minute (0-59): " . ($minute >= 0 && $minute <= 59 ? "✓" : "✗") . "<br>\n";
        echo "Validation seconde (0-59): " . ($second >= 0 && $second <= 59 ? "✓" : "✗") . "<br>\n";

        $dateStr = "$year-$month-$day $hour:$minute:$second";
        echo "Date formatée: $dateStr<br>\n";

        $timestamp = strtotime($dateStr);
        echo "Timestamp: $timestamp<br>\n";
        echo "Date formatée finale: " . date('d/m/Y H:i:s', $timestamp) . "<br>\n";

        if ($timestamp === false || $timestamp <= 0) {
            echo "<strong style='color: red;'>ERREUR: Timestamp invalide!</strong><br>\n";
        }
    } else {
        echo "ERREUR: Regex ne correspond pas<br>\n";
    }
    echo "<hr>\n";
}

function checkCsvData() {
    $csvFile = 'commandes/commandes.csv';
    if (!file_exists($csvFile)) {
        echo "Fichier CSV non trouvé<br>\n";
        return;
    }

    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        echo "Impossible d'ouvrir le CSV<br>\n";
        return;
    }

    $lineNumber = 0;
    while (($line = fgets($handle)) !== false) {
        $lineNumber++;

        // Chercher les références problématiques
        if (strpos($line, 'CMD20250916141686') !== false ||
            strpos($line, 'CMD20250916144593') !== false ||
            strpos($line, 'CMD20250916155379') !== false) {

            $data = str_getcsv($line, ';');
            echo "Ligne $lineNumber: <br>\n";
            echo "Référence: " . ($data[0] ?? 'N/A') . "<br>\n";
            echo "Date commande (colonne 5): " . ($data[5] ?? 'N/A') . "<br>\n";
            echo "Données complètes: " . htmlspecialchars($line) . "<br>\n";
            echo "<hr>\n";
        }
    }
    fclose($handle);
}

function testOrdersList() {
    try {
        $ordersList = new OrdersList();
        $ordersData = $ordersList->loadOrdersData('all');

        echo "Nombre total de commandes chargées: " . count($ordersData['orders']) . "<br>\n";

        // Chercher les commandes problématiques
        foreach ($ordersData['orders'] as $order) {
            if (in_array($order['reference'], ['CMD20250916141686', 'CMD20250916144593', 'CMD20250916155379'])) {
                echo "<h4>Commande: {$order['reference']}</h4>\n";
                echo "Date created_at: " . $order['created_at'] . "<br>\n";
                echo "Timestamp: " . strtotime($order['created_at']) . "<br>\n";
                echo "Date formatée: " . date('d/m/Y H:i:s', strtotime($order['created_at'])) . "<br>\n";
                echo "<hr>\n";
            }
        }

    } catch (Exception $e) {
        echo "Erreur avec OrdersList: " . $e->getMessage() . "<br>\n";
    }
}

?>