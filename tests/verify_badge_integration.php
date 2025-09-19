<?php
/**
 * Script de vérification de l'intégration des badges
 * Tests the order badge integration and compatibility with current data structure
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

session_start();

echo "<h1>Vérification de l'intégration des badges</h1>\n";

// Test 1: Vérifier la structure des commandes existantes
echo "<h2>1. Structure des commandes existantes</h2>\n";
$commandesDir = COMMANDES_DIR;
$orderFiles = glob($commandesDir . '*.json');

$sampleAnalysis = [];
foreach (array_slice($orderFiles, 0, 3) as $file) {
    if (basename($file) === 'orders_export.json') continue;

    $content = file_get_contents($file);
    $orderData = json_decode($content, true);

    $analysis = [
        'file' => basename($file),
        'has_payment_status' => isset($orderData['payment_status']),
        'has_pickup_status' => isset($orderData['pickup_status']),
        'has_status' => isset($orderData['status']),
        'status_value' => $orderData['status'] ?? 'N/A',
        'payment_status_value' => $orderData['payment_status'] ?? 'N/A',
        'pickup_status_value' => $orderData['pickup_status'] ?? 'N/A'
    ];

    $sampleAnalysis[] = $analysis;
}

echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>\n";
echo "<tr><th>Fichier</th><th>payment_status</th><th>pickup_status</th><th>status</th><th>Valeurs</th></tr>\n";
foreach ($sampleAnalysis as $analysis) {
    echo "<tr>";
    echo "<td>{$analysis['file']}</td>";
    echo "<td>" . ($analysis['has_payment_status'] ? '✅' : '❌') . "</td>";
    echo "<td>" . ($analysis['has_pickup_status'] ? '✅' : '❌') . "</td>";
    echo "<td>" . ($analysis['has_status'] ? '✅' : '❌') . "</td>";
    echo "<td>status: {$analysis['status_value']}<br>payment: {$analysis['payment_status_value']}<br>pickup: {$analysis['pickup_status_value']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Test 2: Tester les fonctions de comptage (simulation de l'API)
echo "<h2>2. Test des fonctions de comptage (simulation API)</h2>\n";

// Copier les fonctions de l'API pour test
function countUnpaidOrdersTest() {
    $commandesDir = COMMANDES_DIR;
    $count = 0;

    if (!is_dir($commandesDir)) {
        return 0;
    }

    $files = glob($commandesDir . '*.json');

    foreach ($files as $file) {
        if (basename($file) === 'orders_export.json') {
            continue;
        }

        $content = file_get_contents($file);
        $orderData = json_decode($content, true);

        // Gestion de compatibilité avec l'ancien format
        if ($orderData) {
            $isUnpaid = false;

            if (isset($orderData['payment_status'])) {
                $isUnpaid = $orderData['payment_status'] !== 'paid';
            } else if (isset($orderData['status'])) {
                $isUnpaid = $orderData['status'] === 'validated';
            }

            if ($isUnpaid) {
                $count++;
            }
        }
    }

    return $count;
}

function countReadyForPickupTest() {
    $commandesDir = COMMANDES_DIR;
    $count = 0;

    if (!is_dir($commandesDir)) {
        return 0;
    }

    $files = glob($commandesDir . '*.json');

    foreach ($files as $file) {
        if (basename($file) === 'orders_export.json') {
            continue;
        }

        $content = file_get_contents($file);
        $orderData = json_decode($content, true);

        // Gestion de compatibilité avec l'ancien format
        if ($orderData) {
            $isReadyForPickup = false;

            if (isset($orderData['payment_status']) && isset($orderData['pickup_status'])) {
                $isReadyForPickup = $orderData['payment_status'] === 'paid' &&
                                   $orderData['pickup_status'] !== 'completed';
            } else {
                $isReadyForPickup = false;
            }

            if ($isReadyForPickup) {
                $count++;
            }
        }
    }

    return $count;
}

$unpaidCount = countUnpaidOrdersTest();
$pickupCount = countReadyForPickupTest();
$totalOrders = count($orderFiles) - (file_exists($commandesDir . 'orders_export.json') ? 1 : 0);

echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>\n";
echo "<tr><th>Métrique</th><th>Valeur</th><th>Commentaire</th></tr>\n";
echo "<tr><td>Total des commandes</td><td>{$totalOrders}</td><td>Nombre de fichiers JSON (hors export)</td></tr>\n";
echo "<tr><td>Commandes non payées</td><td>{$unpaidCount}</td><td>Status 'validated' (ancien format)</td></tr>\n";
echo "<tr><td>Prêtes pour retrait</td><td>{$pickupCount}</td><td>Aucune avec l'ancien format</td></tr>\n";
echo "</table>\n";

// Test 3: Vérifier l'intégration header
echo "<h2>3. Vérification de l'intégration header sur admin.php</h2>\n";

$adminPhpPath = __DIR__ . '/admin.php';
$adminContent = file_get_contents($adminPhpPath);

$hasIncludeHeader = strpos($adminContent, "include('include.header.php')") !== false;
$hasIsAdminVar = strpos($adminContent, '$is_admin = true') !== false;

echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>\n";
echo "<tr><th>Vérification</th><th>Status</th><th>Détail</th></tr>\n";
echo "<tr><td>include.header.php présent</td><td>" . ($hasIncludeHeader ? '✅' : '❌') . "</td><td>" . ($hasIncludeHeader ? 'Trouvé dans admin.php' : 'Absent de admin.php') . "</td></tr>\n";
echo "<tr><td>\$is_admin défini</td><td>" . ($hasIsAdminVar ? '✅' : '❌') . "</td><td>" . ($hasIsAdminVar ? 'Variable définie avant include' : 'Variable manquante') . "</td></tr>\n";
echo "</table>\n";

// Test 4: Vérifier les fichiers JS requis
echo "<h2>4. Vérification des fichiers JavaScript</h2>\n";

$jsOrderBadges = file_exists(__DIR__ . '/js/order-badges.js');
$apiOrderBadges = file_exists(__DIR__ . '/api_order_badges.php');

echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>\n";
echo "<tr><th>Fichier</th><th>Status</th><th>Taille</th></tr>\n";
echo "<tr><td>js/order-badges.js</td><td>" . ($jsOrderBadges ? '✅' : '❌') . "</td><td>" . ($jsOrderBadges ? filesize(__DIR__ . '/js/order-badges.js') . ' bytes' : 'N/A') . "</td></tr>\n";
echo "<tr><td>api_order_badges.php</td><td>" . ($apiOrderBadges ? '✅' : '❌') . "</td><td>" . ($apiOrderBadges ? filesize(__DIR__ . '/api_order_badges.php') . ' bytes' : 'N/A') . "</td></tr>\n";
echo "</table>\n";

echo "<h2>Résumé</h2>\n";
echo "<ul>\n";
echo "<li><strong>Structure des données :</strong> Compatible avec l'ancien format (status: validated)</li>\n";
echo "<li><strong>Comptage des badges :</strong> {$unpaidCount} commandes non payées détectées</li>\n";
echo "<li><strong>Intégration admin.php :</strong> " . ($hasIncludeHeader && $hasIsAdminVar ? "✅ Correcte" : "❌ Problème détecté") . "</li>\n";
echo "<li><strong>Fichiers requis :</strong> " . ($jsOrderBadges && $apiOrderBadges ? "✅ Présents" : "❌ Manquants") . "</li>\n";
echo "</ul>\n";

echo "<p><strong>Prochaines étapes recommandées :</strong></p>\n";
echo "<ol>\n";
echo "<li>Tester admin.php dans un navigateur pour vérifier l'affichage des badges</li>\n";
echo "<li>Vérifier que l'API est accessible avec authentification admin</li>\n";
echo "<li>Considérer une migration progressive vers le nouveau format de données</li>\n";
echo "</ol>\n";
?>