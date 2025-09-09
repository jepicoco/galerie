<?php
/**
 * Script de correction pour nettoyer les BOM UTF-8 multiples
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';

echo "=== CORRECTION BOM UTF-8 MULTIPLES ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$csvFile = 'commandes/commandes.csv';

if (!file_exists($csvFile)) {
    echo "❌ Fichier CSV introuvable: $csvFile\n";
    exit(1);
}

echo "1. ANALYSE DU PROBLÈME\n";
echo "----------------------\n";

// Analyser le contenu actuel
$content = file_get_contents($csvFile);
$originalSize = strlen($content);
$bomCount = substr_count($content, "\xEF\xBB\xBF");

echo "Taille fichier original: $originalSize octets\n";
echo "Nombre de BOM UTF-8 détectés: $bomCount\n";

if ($bomCount <= 1) {
    echo "✅ Le fichier est déjà correct (0 ou 1 BOM)\n";
    exit(0);
}

echo "❌ Problème confirmé: $bomCount BOM détectés au lieu de 1\n\n";

echo "2. NETTOYAGE EN COURS\n";
echo "---------------------\n";

// Supprimer tous les BOM
$cleanContent = str_replace("\xEF\xBB\xBF", "", $content);
echo "✓ Suppression de tous les BOM existants\n";

// Ajouter UN SEUL BOM au début
$fixedContent = "\xEF\xBB\xBF" . $cleanContent;
echo "✓ Ajout d'un BOM unique au début\n";

// Vérifications avant sauvegarde
$newSize = strlen($fixedContent);
$lines = explode("\n", $cleanContent);
$headerLine = $lines[0];

echo "✓ Nouvelle taille: $newSize octets (économie: " . ($originalSize - $newSize) . " octets)\n";
echo "✓ En-tête nettoyé: " . substr($headerLine, 0, 50) . "...\n";
echo "✓ Nombre de lignes préservées: " . count($lines) . "\n";

// Sauvegarder le fichier corrigé
$result = file_put_contents($csvFile, $fixedContent);

if ($result === false) {
    echo "❌ ERREUR: Impossible de sauvegarder le fichier corrigé\n";
    exit(1);
}

echo "\n3. VALIDATION POST-CORRECTION\n";
echo "-----------------------------\n";

// Revalider le fichier corrigé
$validationContent = file_get_contents($csvFile);
$newBomCount = substr_count($validationContent, "\xEF\xBB\xBF");
$finalSize = strlen($validationContent);

echo "BOM après correction: $newBomCount\n";
echo "Taille finale: $finalSize octets\n";

// Tester le parsing CSV
$lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines !== false && count($lines) > 0) {
    $headerTest = str_replace("\xEF\xBB\xBF", "", $lines[0]);
    $columns = str_getcsv($headerTest, ';');
    
    echo "✓ Parsing CSV réussi\n";
    echo "✓ Nombre de colonnes: " . count($columns) . "\n";
    echo "✓ Première colonne: " . $columns[0] . "\n";
    
    if ($columns[0] === 'REF') {
        echo "✅ Structure CSV validée - Colonne REF correctement identifiée\n";
    } else {
        echo "❌ ATTENTION: Première colonne n'est pas 'REF' mais '{$columns[0]}'\n";
    }
} else {
    echo "❌ ERREUR: Impossible de parser le fichier corrigé\n";
    exit(1);
}

echo "\n4. RÉSULTATS\n";
echo "------------\n";

if ($newBomCount === 1) {
    echo "✅ SUCCÈS: Correction appliquée avec succès\n";
    echo "   - BOM multiples supprimés: " . ($bomCount - 1) . "\n";
    echo "   - Espace libéré: " . ($originalSize - $finalSize) . " octets\n";
    echo "   - Structure CSV préservée\n";
    echo "   - Fichier prêt pour utilisation\n";
    
    echo "\n📋 IMPACT ATTENDU:\n";
    echo "- Les fonctions CSV pourront maintenant parser correctement\n";
    echo "- Les mises à jour de statuts de commandes fonctionneront\n";
    echo "- Les commandes payées (statut 'paid') n'apparaîtront plus dans admin_orders.php\n";
    
} else {
    echo "❌ ÉCHEC: Le fichier n'a pas été correctement corrigé\n";
    echo "   BOM attendu: 1, trouvé: $newBomCount\n";
    exit(1);
}

echo "\n=== CORRECTION TERMINÉE ===\n";
?>