<?php
/**
 * Test spécifique d'ajout de la commande validated au fichier préparateur
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "=== TEST AJOUT COMMANDE VALIDATED AU FICHIER PRÉPARATEUR ===\n\n";

// Récupérer la première commande validated du CSV
$csvFile = COMMANDES_DIR . 'commandes.csv';
$preparerFile = COMMANDES_DIR . 'commandes_a_preparer.csv';

$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle, 0, ';');
$refIndex = array_search('REF', $header);
$statusIndex = array_search('Statut commande', $header);

$validatedRef = null;
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $ref = $row[$refIndex] ?? '';
    $status = $row[$statusIndex] ?? '';
    
    if ($status === 'validated' && !$validatedRef) {
        $validatedRef = $ref;
        break;
    }
}
fclose($handle);

if ($validatedRef) {
    echo "Commande validated trouvée: $validatedRef\n";
    
    // Vider le fichier préparateur pour un test propre
    if (file_exists($preparerFile)) {
        echo "Suppression de l'ancien fichier préparateur...\n";
        unlink($preparerFile);
    }
    
    // Tester l'ajout
    echo "Test d'ajout de la commande au fichier préparateur...\n";
    $result = addOrderToPreparerFile($validatedRef);
    
    echo "Résultat: " . ($result ? "✅ SUCCÈS" : "❌ ÉCHEC") . "\n";
    
    // Vérifier le contenu
    if (file_exists($preparerFile)) {
        $content = file_get_contents($preparerFile);
        echo "Fichier créé avec " . strlen($content) . " caractères\n";
        
        $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
        echo "Nombre de lignes: " . count($lines) . "\n";
        
        echo "Contenu généré:\n";
        foreach ($lines as $i => $line) {
            echo "  " . ($i + 1) . ": " . $line . "\n";
        }
    }
} else {
    echo "❌ Aucune commande avec statut 'validated' trouvée\n";
}
?>