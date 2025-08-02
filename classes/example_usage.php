<?php
/**
 * Exemple d'utilisation du système d'autoloading
 * Montre comment utiliser les classes sans les inclure manuellement
 */

// Définir l'accès (nécessaire pour toutes les classes)
define('GALLERY_ACCESS', true);

// Inclure la configuration
require_once '../config.php';

// Inclure l'autoloader - à partir de maintenant, plus besoin d'includes manuels !
require_once 'autoload.php';

echo "<h1>Exemple d'utilisation de l'autoloader</h1>\n";

// ==========================================
// EXEMPLE 1: Utilisation du Logger
// ==========================================
echo "<h2>1. Utilisation du Logger (Singleton)</h2>\n";

// Pas besoin de require_once 'logger.class.php' !
$logger = Logger::getInstance();
$logger->info("Test du logger via autoloader");
$logger->debug("Message de debug", ['context' => 'example']);

echo "✓ Logger utilisé sans inclusion manuelle<br>\n";

// ==========================================
// EXEMPLE 2: Gestion CSV
// ==========================================
echo "<h2>2. Gestion de fichiers CSV</h2>\n";

// Pas besoin de require_once 'csv.class.php' !
$csv = new CsvHandler();

// Exemple de données
$testData = [
    ['Nom', 'Prénom', 'Email'],
    ['Dupont', 'Jean', 'jean.dupont@example.com'],
    ['Martin', 'Marie', 'marie.martin@example.com']
];

// Test d'écriture (en mémoire pour l'exemple)
$tempFile = 'temp_test.csv';
if ($csv->write($tempFile, array_slice($testData, 1), $testData[0], false, true)) {
    echo "✓ Fichier CSV créé avec succès<br>\n";
    
    // Test de lecture
    $readData = $csv->read($tempFile, true);
    if ($readData !== false) {
        echo "✓ Fichier CSV lu avec succès (" . $readData['total_lines'] . " lignes)<br>\n";
    }
    
    // Nettoyer
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}

// ==========================================
// EXEMPLE 3: Gestion d'une commande
// ==========================================
echo "<h2>3. Gestion d'une commande</h2>\n";

// Pas besoin de require_once 'order.class.php' !
$order = new Order();

// Générer une référence
$reference = $order->generateReference();
echo "Référence générée: $reference<br>\n";

// Définir des données de test
$order->setData([
    'reference' => $reference,
    'lastname' => 'Test',
    'firstname' => 'Utilisateur',
    'email' => 'test@example.com',
    'phone' => '0123456789',
    'activity_key' => 'photo_standard',
    'photo_name' => 'IMG_001.jpg',
    'quantity' => 2,
    'total_price' => 4.0
]);

echo "✓ Commande créée: " . $order->getReference() . "<br>\n";
echo "✓ Date de création: " . $order->getCreationDate() . "<br>\n";

// ==========================================
// EXEMPLE 4: Gestion de listes de commandes
// ==========================================
echo "<h2>4. Gestion de listes de commandes</h2>\n";

// Pas besoin de require_once 'orders.liste.class.php' !
$ordersList = new OrdersList();

// Les méthodes seraient utilisées avec de vraies données CSV
echo "✓ Liste de commandes instanciée<br>\n";
echo "✓ Prêt pour loadOrdersData(), calculateStats(), etc.<br>\n";

// ==========================================
// EXEMPLE 5: Utilisation d'alias
// ==========================================
echo "<h2>5. Utilisation d'alias</h2>\n";

// Utiliser les alias pour plus de simplicité
$csv2 = new CSV(); // Alias pour CsvHandler
$log2 = Log::getInstance(); // Alias pour Logger
$orders2 = new Orders(); // Alias pour OrdersList

echo "✓ Alias CSV fonctionne: " . get_class($csv2) . "<br>\n";
echo "✓ Alias Log fonctionne: " . get_class($log2) . "<br>\n";
echo "✓ Alias Orders fonctionne: " . get_class($orders2) . "<br>\n";

// ==========================================
// EXEMPLE 6: Workflow complet
// ==========================================
echo "<h2>6. Workflow complet d'exemple</h2>\n";

try {
    // 1. Créer une commande
    $newOrder = new Order();
    $ref = $newOrder->generateReference();
    $newOrder->setReference($ref);
    
    // 2. Logger l'action
    $logger->adminAction("Création d'une nouvelle commande", ['reference' => $ref]);
    
    // 3. Gérer des exports CSV
    $csvExport = new CsvHandler();
    
    // 4. Calculer des statistiques
    $stats = new OrdersList();
    
    echo "✓ Workflow complet exécuté sans erreur<br>\n";
    echo "✓ Toutes les classes chargées automatiquement<br>\n";
    
} catch (Exception $e) {
    echo "✗ Erreur dans le workflow: " . $e->getMessage() . "<br>\n";
}

// ==========================================
// INFORMATIONS SYSTÈME
// ==========================================
echo "<h2>Informations système</h2>\n";

$status = getAutoloaderStatus();
echo "Autoloader Galla enregistré: " . ($status['galla_registered'] ? 'OUI' : 'NON') . "<br>\n";
echo "Nombre total d'autoloaders: " . $status['total_autoloaders'] . "<br>\n";
echo "Classes disponibles: " . $status['available_classes'] . "<br>\n";

echo "<h3>Avantages de ce système:</h3>\n";
echo "<ul>\n";
echo "<li>✓ Plus besoin de require_once pour chaque classe</li>\n";
echo "<li>✓ Chargement automatique et à la demande</li>\n";
echo "<li>✓ Support des alias pour faciliter l'utilisation</li>\n";
echo "<li>✓ Gestion d'erreurs intégrée</li>\n";
echo "<li>✓ Performance optimisée (pas de chargement inutile)</li>\n";
echo "<li>✓ Extensible pour de nouvelles classes</li>\n";
echo "</ul>\n";

?>