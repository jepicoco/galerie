<?php
/**
 * Script de validation du système de création automatique des CSV
 * Affiche l'état des fichiers et valide l'implémentation
 */

define('GALLERY_ACCESS', true);

// Test d'inclusion et vérification des constantes
try {
    require_once 'config.php';
    echo "✅ Configuration chargée\n";
} catch (Exception $e) {
    echo "❌ Erreur chargement config: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== VALIDATION SYSTÈME CRÉATION CSV ===\n\n";

// Vérifier les constantes requises
$requiredConstants = ['COMMANDES_DIR', 'LOGS_ENABLED'];
echo "1. Vérification des constantes:\n";
foreach ($requiredConstants as $constant) {
    if (defined($constant)) {
        $value = constant($constant);
        echo "   ✅ $constant = " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
    } else {
        echo "   ❌ $constant non définie\n";
    }
}

// Vérifier la fonction createRequiredCSVFiles
echo "\n2. Vérification de la fonction:\n";
if (function_exists('createRequiredCSVFiles')) {
    echo "   ✅ createRequiredCSVFiles() existe\n";
} else {
    echo "   ❌ createRequiredCSVFiles() manquante\n";
    exit(1);
}

// Tester la fonction createRequiredDirectories
echo "\n3. Test création dossiers:\n";
if (function_exists('createRequiredDirectories')) {
    $dirResult = createRequiredDirectories();
    echo "   " . ($dirResult ? "✅" : "❌") . " createRequiredDirectories()\n";
} else {
    echo "   ❌ createRequiredDirectories() manquante\n";
}

// Liste des fichiers CSV attendus avec leurs chemins complets
$expectedCSVFiles = [
    'commandes.csv' => COMMANDES_DIR . 'commandes.csv',
    'commandes_reglees.csv' => COMMANDES_DIR . 'commandes_reglees.csv',
    'commandes_a_preparer.csv' => COMMANDES_DIR . 'commandes_a_preparer.csv'
];

echo "\n4. État actuel des fichiers CSV:\n";
$existingFiles = 0;
foreach ($expectedCSVFiles as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        echo "   ✅ $name ($size bytes)\n";
        $existingFiles++;
    } else {
        echo "   ❌ $name (manquant)\n";
    }
}

echo "\n5. Test de la fonction createRequiredCSVFiles():\n";
try {
    $result = createRequiredCSVFiles();
    
    echo "   Résultat: " . ($result['success'] ? "✅ Succès" : "❌ Échec") . "\n";
    
    if (!empty($result['created'])) {
        echo "   Fichiers créés:\n";
        foreach ($result['created'] as $created) {
            echo "     ➕ " . $created['file'] . " - " . $created['description'] . "\n";
        }
    }
    
    if (!empty($result['errors'])) {
        echo "   Erreurs:\n";
        foreach ($result['errors'] as $error) {
            echo "     ⚠️ $error\n";
        }
    }
    
    if (empty($result['created']) && empty($result['errors'])) {
        echo "   ℹ️ Tous les fichiers existent déjà\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n6. État final des fichiers CSV:\n";
$finalExisting = 0;
foreach ($expectedCSVFiles as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $finalExisting++;
        
        // Vérifier le contenu du fichier
        $content = file_get_contents($path, false, null, 0, 100);
        $hasBOM = substr($content, 0, 3) === "\xEF\xBB\xBF";
        $hasHeader = strpos($content, ';') !== false;
        
        echo "   ✅ $name ($size bytes) - BOM:" . ($hasBOM ? "✅" : "❌") . " Header:" . ($hasHeader ? "✅" : "❌") . "\n";
    } else {
        echo "   ❌ $name (manquant)\n";
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers CSV présents: $finalExisting/" . count($expectedCSVFiles) . "\n";

if ($finalExisting === count($expectedCSVFiles)) {
    echo "🎉 Système de création automatique validé\n";
    echo "✅ Tous les fichiers CSV requis sont présents\n";
} else {
    echo "⚠️ Système incomplet - " . (count($expectedCSVFiles) - $finalExisting) . " fichier(s) manquant(s)\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
?>