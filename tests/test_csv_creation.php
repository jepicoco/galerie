<?php
/**
 * Script de test pour la création automatique des fichiers CSV
 * Teste la fonction createRequiredCSVFiles() du système
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';

echo "<h1>Test de création automatique des fichiers CSV</h1>\n";
echo "<p>Script de test pour vérifier la création des fichiers CSV au chargement du système.</p>\n\n";

// Lister les fichiers CSV attendus
$expectedFiles = [
    COMMANDES_DIR . 'commandes.csv',
    COMMANDES_DIR . 'commandes_reglees.csv', 
    COMMANDES_DIR . 'commandes_a_preparer.csv'
];

echo "<h2>1. État initial des fichiers CSV</h2>\n";
foreach ($expectedFiles as $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅ Existe" : "❌ Manquant";
    $size = $exists ? " (" . filesize($file) . " bytes)" : "";
    echo "<p><strong>" . basename($file) . ":</strong> $status$size</p>\n";
}

// Sauvegarder les fichiers existants pour les restaurer après le test
$backups = [];
echo "\n<h2>2. Sauvegarde des fichiers existants</h2>\n";
foreach ($expectedFiles as $file) {
    if (file_exists($file)) {
        $backupFile = $file . '.backup.' . date('Y-m-d_H-i-s');
        if (copy($file, $backupFile)) {
            $backups[$file] = $backupFile;
            echo "<p>✅ Sauvegardé: " . basename($file) . " → " . basename($backupFile) . "</p>\n";
        } else {
            echo "<p>❌ Erreur sauvegarde: " . basename($file) . "</p>\n";
        }
    }
}

// Supprimer les fichiers pour tester la création
echo "\n<h2>3. Suppression temporaire des fichiers pour test</h2>\n";
foreach ($expectedFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "<p>✅ Supprimé: " . basename($file) . "</p>\n";
        } else {
            echo "<p>❌ Impossible de supprimer: " . basename($file) . "</p>\n";
        }
    }
}

// Tester la création
echo "\n<h2>4. Test de la fonction createRequiredCSVFiles()</h2>\n";
$result = createRequiredCSVFiles();

if ($result['success']) {
    echo "<p>✅ <strong>Fonction exécutée avec succès</strong></p>\n";
    
    if (!empty($result['created'])) {
        echo "<h3>Fichiers créés:</h3>\n";
        foreach ($result['created'] as $created) {
            echo "<p>➕ <strong>" . $created['file'] . "</strong>: " . $created['description'] . "</p>\n";
        }
    } else {
        echo "<p>ℹ️ Aucun fichier à créer (tous existent déjà)</p>\n";
    }
} else {
    echo "<p>❌ <strong>Erreurs détectées:</strong></p>\n";
    foreach ($result['errors'] as $error) {
        echo "<p>⚠️ $error</p>\n";
    }
}

// Vérifier les fichiers créés
echo "\n<h2>5. Vérification des fichiers après création</h2>\n";
foreach ($expectedFiles as $file) {
    $exists = file_exists($file);
    if ($exists) {
        $size = filesize($file);
        $content = file_get_contents($file, false, null, 0, 200); // Premier 200 chars
        $hasHeader = !empty($content) && strpos($content, ';') !== false;
        $hasBOM = substr($content, 0, 3) === "\xEF\xBB\xBF";
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0;'>\n";
        echo "<p><strong>" . basename($file) . ":</strong> ✅ Créé ($size bytes)</p>\n";
        echo "<p>🔤 BOM UTF-8: " . ($hasBOM ? "✅ Présent" : "❌ Absent") . "</p>\n";
        echo "<p>📋 En-tête: " . ($hasHeader ? "✅ Détecté" : "❌ Manquant") . "</p>\n";
        echo "<pre style='background: #f5f5f5; padding: 5px; font-size: 0.9em;'>" . htmlspecialchars(substr($content, 0, 150)) . "...</pre>\n";
        echo "</div>\n";
    } else {
        echo "<p><strong>" . basename($file) . ":</strong> ❌ Non créé</p>\n";
    }
}

// Restaurer les sauvegardes
echo "\n<h2>6. Restauration des fichiers originaux</h2>\n";
if (!empty($backups)) {
    foreach ($backups as $original => $backup) {
        if (file_exists($backup)) {
            // Supprimer le fichier test d'abord
            if (file_exists($original)) {
                unlink($original);
            }
            
            if (rename($backup, $original)) {
                echo "<p>✅ Restauré: " . basename($original) . "</p>\n";
            } else {
                echo "<p>❌ Erreur restauration: " . basename($original) . "</p>\n";
            }
        }
    }
} else {
    echo "<p>ℹ️ Aucune sauvegarde à restaurer</p>\n";
}

echo "\n<h2>7. État final</h2>\n";
foreach ($expectedFiles as $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅ Existe" : "❌ Absent";
    $size = $exists ? " (" . filesize($file) . " bytes)" : "";
    echo "<p><strong>" . basename($file) . ":</strong> $status$size</p>\n";
}

echo "\n<hr>\n";
echo "<p><strong>✅ Test terminé</strong></p>\n";
echo "<p>Le système créera automatiquement les fichiers CSV manquants au chargement.</p>\n";
?>